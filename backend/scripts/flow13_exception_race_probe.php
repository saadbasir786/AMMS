<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Actions\Attendance\RecordManualAttendance;
use App\Actions\Flow13\ApplyMeetingCancellation;
use App\Actions\Flow13\ApproveMeetingExceptionReview;
use App\Actions\Flow13\CreateMeetingExceptionCase;
use App\Actions\Flow13\RequestMeetingExceptionCorrection;
use App\Actions\Flow13\SubmitMeetingExceptionCase;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionReview;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (DB::connection()->getDriverName() !== 'mysql') {
    fwrite(STDERR, "FLOW13_RACE_REQUIRES_MYSQL\n");
    exit(2);
}

$owner = User::where('email', 'tech.coordinator@example.test')->firstOrFail();
$director = User::where('email', 'tech.director@example.test')->firstOrFail();
$unit = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$employee = Employee::findOrFail($director->employee_id);

function fixture(User $owner, OrganizationalUnit $unit, Employee $employee): Meeting {
    $request = MeetingRequest::create(['request_number' => 'FLOW13-RACE-'.uniqid(), 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $owner->id, 'subject' => 'FLOW-13 MariaDB race fixture', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
    $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW13-RACE-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->addDays(12)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW13 RACE', 'status' => 'SCHEDULED']);
    MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'REQUESTING']);
    MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
    $schedule = app(ScheduleVersionService::class);
    $version = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $owner->id);
    $schedule->applyToMeeting($meeting, $version);
    app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);
    return $meeting->fresh();
}

function prepareCase(Meeting $meeting, User $owner, string $type, string $reason, bool $submit = false): MeetingExceptionCase {
    $case = app(CreateMeetingExceptionCase::class)->execute($meeting, $owner, $type, $reason, false, null, null);
    if ($submit) {
        $case = app(SubmitMeetingExceptionCase::class)->execute($case, $owner);
    }
    return $case->fresh(['currentVersion.reviews']);
}

function worker(string $name, Closure $operation): array {
    $path = sys_get_temp_dir().'/flow13-race-'.getmypid().'-'.Str::random(8).'.json';
    $pid = pcntl_fork();
    if ($pid === -1) throw new RuntimeException('pcntl_fork failed');
    if ($pid === 0) {
        $result = ['worker' => $name];
        try { $result['outcome'] = $operation(); $result['ok'] = true; }
        catch (Throwable $e) { $result['ok'] = false; $result['error'] = ['class' => get_class($e), 'message' => $e->getMessage(), 'code' => $e->getCode()]; }
        file_put_contents($path, json_encode($result, JSON_UNESCAPED_UNICODE));
        exit(0);
    }
    pcntl_waitpid($pid, $status);
    return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

$race = [];
$meeting = fixture($owner, $unit, $employee);
$results = [worker('create-a', fn () => app(CreateMeetingExceptionCase::class)->execute($meeting->fresh(), $owner, 'POSTPONEMENT', 'تعویق رقابتی اول.')->id), worker('create-b', fn () => app(CreateMeetingExceptionCase::class)->execute($meeting->fresh(), $owner, 'POSTPONEMENT', 'تعویق رقابتی دوم.')->id)];
$race['active_case_creation'] = ['workers' => $results, 'active_case_count' => MeetingExceptionCase::where('meeting_id', $meeting->id)->where('active_marker', 1)->count()];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'CANCELLATION', 'لغو برای آزمون درخواست همزمان.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$case->refresh();
$results = [worker('apply-a', fn () => app(ApplyMeetingCancellation::class)->execute($case->fresh(), $owner)->status), worker('apply-b', fn () => app(ApplyMeetingCancellation::class)->execute($case->fresh(), $owner)->status)];
$race['duplicate_application'] = ['workers' => $results, 'case_status' => $case->fresh()->status, 'meeting_status' => $meeting->fresh()->status];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'POSTPONEMENT', 'اصلاح همزمان پرونده.', true);
$review = $case->currentVersion->reviews->first();
$results = [worker('approve', fn () => app(ApproveMeetingExceptionReview::class)->execute($review->fresh(), $director)->status), worker('correction', fn () => app(RequestMeetingExceptionCorrection::class)->execute($review->fresh(), $director, 'نیازمند اصلاح همزمان.')->status)];
$race['approval_vs_correction'] = ['workers' => $results, 'case_status' => $case->fresh()->status, 'review_status' => $review->fresh()->status];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'CANCELLATION', 'آزمون لغو همزمان با حضور.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$results = [worker('apply-cancellation', fn () => app(ApplyMeetingCancellation::class)->execute($case->fresh(), $owner)->status), worker('manual-attendance', fn () => app(RecordManualAttendance::class)->execute($owner, $meeting->fresh(), $employee->id, 'حضور رقابتی.')->id)];
$race['cancellation_vs_attendance'] = ['workers' => $results, 'meeting_status' => $meeting->fresh()->status, 'official_attendance_rows' => $meeting->fresh()->attendanceRecords()->count()];

$allWorkers = collect($race)->flatMap(fn (array $group): array => $group['workers'])->values();
$oneWinnerOneLoser = fn (array $workers): bool => count(array_filter($workers, fn (array $worker): bool => $worker['ok'])) === 1 && count(array_filter($workers, fn (array $worker): bool => ! $worker['ok'])) === 1;
$race['pass'] = $race['active_case_creation']['active_case_count'] === 1
    && $oneWinnerOneLoser($race['active_case_creation']['workers'])
    && $oneWinnerOneLoser($race['duplicate_application']['workers'])
    && $race['duplicate_application']['case_status'] === 'APPLIED'
    && $race['duplicate_application']['meeting_status'] === 'CANCELLED'
    && $oneWinnerOneLoser($race['approval_vs_correction']['workers'])
    && in_array($race['approval_vs_correction']['case_status'], ['READY_TO_APPLY', 'RETURNED_FOR_CORRECTION'], true)
    && $oneWinnerOneLoser($race['cancellation_vs_attendance']['workers'])
    && $race['cancellation_vs_attendance']['meeting_status'] === 'CANCELLED'
    && $race['cancellation_vs_attendance']['official_attendance_rows'] === 0;
$race['race_group_count'] = 4;
echo json_encode($race, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
