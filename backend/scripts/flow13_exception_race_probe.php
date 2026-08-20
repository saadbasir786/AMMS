<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Actions\Attendance\RecordManualAttendance;
use App\Actions\Flow13\ApplyMeetingCancellation;
use App\Actions\Flow13\ApplyMeetingPostponement;
use App\Actions\Flow13\ApplyMeetingReopening;
use App\Actions\Flow13\ResumePostponedMeeting;
use App\Actions\Meetings\ConfirmMeetingParticipantChange;
use App\Actions\Meetings\ConfirmMeetingReschedule;
use App\Actions\Terminals\ActivateTerminalMeetingSession;
use App\Actions\Meetings\StartMeetingExecution;
use App\Actions\Flow13\ApproveMeetingExceptionReview;
use App\Actions\Flow13\CreateMeetingExceptionCase;
use App\Actions\Flow13\RequestMeetingExceptionCorrection;
use App\Actions\Flow13\SubmitMeetingExceptionCase;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionReview;
use App\Models\MeetingParticipant;
use App\Models\MeetingParticipantChangeItem;
use App\Models\MeetingRequest;
use App\Models\MeetingTerminalAssignment;
use App\Models\Organization;
use App\Models\Terminal;
use App\Models\MeetingRescheduleAcknowledgement;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingRescheduleProposal;
use App\Models\MeetingParticipantChangeCase;
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
$replacementEmployee = Employee::where('email', 'tech.exec@example.test')->where('status', 'ACTIVE')->firstOrFail();

function fixture(User $owner, OrganizationalUnit $unit, Employee $employee): Meeting {
    $request = MeetingRequest::create(['request_number' => 'FLOW13-RACE-'.uniqid(), 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $owner->id, 'subject' => 'FLOW-13 MariaDB race fixture', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
    $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW13-RACE-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->addDays(800 + random_int(0, 500))->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW13 RACE', 'status' => 'SCHEDULED']);
    MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'REQUESTING']);
    MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
    $schedule = app(ScheduleVersionService::class);
    $version = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $owner->id);
    $schedule->applyToMeeting($meeting, $version);
    app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);
    return $meeting->fresh();
}

function prepareCase(Meeting $meeting, User $owner, string $type, string $reason, bool $submit = false, bool $replacementExpected = false): MeetingExceptionCase {
    $case = app(CreateMeetingExceptionCase::class)->execute($meeting, $owner, $type, $reason, $replacementExpected, null, null);
    if ($submit) {
        $case = app(SubmitMeetingExceptionCase::class)->execute($case, $owner);
    }
    return $case->fresh(['currentVersion.reviews']);
}

function participantChangeFixture(Meeting $meeting, User $owner, OrganizationalUnit $unit, Employee $replacement): MeetingParticipantChangeCase
{
    $case = MeetingParticipantChangeCase::create(['meeting_id' => $meeting->id, 'owner_type' => 'UNIT', 'owner_unit_id' => $unit->id, 'baseline_participant_version_id' => $meeting->current_participant_version_id, 'baseline_schedule_version_id' => $meeting->current_schedule_version_id, 'initiated_by_user_id' => $owner->id, 'reason' => 'FLOW-08 cancellation race.', 'status' => 'PENDING_CONFIRMATION']);
    MeetingParticipantChangeItem::create(['change_case_id' => $case->id, 'operation' => 'ADD', 'new_employee_id' => $replacement->id, 'participant_role' => 'PARTICIPATING']);
    return $case->fresh(['items', 'baselineParticipantVersion.members', 'meeting.currentParticipantVersion.members']);
}

function terminalAssignmentFixture(Meeting $meeting, User $owner): array
{
    $terminal = Terminal::create(['uuid' => (string) Str::uuid(), 'organization_id' => Organization::firstOrFail()->id, 'code' => 'FLOW13-RACE-TERM-'.strtoupper(Str::random(8)), 'name' => 'FLOW-13 race terminal', 'serial_number' => 'FLOW13-RACE-SERIAL-'.strtoupper(Str::random(8)), 'manufacturer' => 'TEST', 'model' => 'FLOW13', 'platform' => 'TEST', 'os_version' => '1', 'app_version' => '1', 'status' => 'ACTIVE', 'registered_by_user_id' => $owner->id, 'registered_at' => now()]);
    $assignment = MeetingTerminalAssignment::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'terminal_id' => $terminal->id, 'baseline_schedule_version_id' => $meeting->current_schedule_version_id, 'baseline_participant_version_id' => $meeting->current_participant_version_id, 'valid_from' => now()->subMinute(), 'valid_until' => now()->addHour(), 'status' => 'ASSIGNED', 'assigned_by_user_id' => $owner->id, 'assigned_at' => now()]);
    return [$terminal, $assignment];
}

function rescheduleFixture(Meeting $meeting, User $owner, OrganizationalUnit $unit): MeetingRescheduleCase
{
    $current = $meeting->fresh();
    $case = MeetingRescheduleCase::create(['meeting_id' => $meeting->id, 'baseline_schedule_version_id' => $current->current_schedule_version_id, 'initiated_by_user_id' => $owner->id, 'initiated_by_unit_id' => $unit->id, 'reason' => 'FLOW-13 stale schedule race.', 'governance_mode' => 'LOCAL', 'status' => 'READY_TO_CONFIRM']);
    $meeting->forceFill(['active_reschedule_case_id' => $case->id])->save();
    $proposal = MeetingRescheduleProposal::create(['reschedule_case_id' => $case->id, 'meeting_id' => $meeting->id, 'proposed_by_user_id' => $owner->id, 'proposed_by_unit_id' => $unit->id, 'proposal_type' => 'PROPOSED', 'meeting_date' => now()->addDays(1000 + random_int(0, 1000))->format('Y-m-d'), 'start_time' => '11:00', 'end_time' => '12:00', 'location' => 'FLOW13 RESCHEDULE', 'comment' => 'race proposal', 'status' => 'ACTIVE']);
    MeetingRescheduleAcknowledgement::create(['proposal_id' => $proposal->id, 'organizational_unit_id' => $unit->id, 'acknowledged_by_user_id' => $owner->id, 'acknowledged_at' => now()]);
    return $case->fresh(['proposals.acknowledgements']);
}

function attempt(Closure $operation): array
{
    try { return ['ok' => true, 'outcome' => $operation()]; }
    catch (Throwable $e) { return ['ok' => false, 'error' => ['class' => get_class($e), 'message' => $e->getMessage(), 'code' => $e->getCode()]]; }
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

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'CANCELLATION', 'لغو در برابر شروع جلسه.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$cancelFirst = attempt(fn () => app(ApplyMeetingCancellation::class)->execute($case->fresh(), $owner)->status);
$startAfterCancel = attempt(fn () => app(StartMeetingExecution::class)->execute($owner, $meeting->fresh())->status);
$race['cancellation_vs_execution']['cancellation_first'] = ['cancellation' => $cancelFirst, 'execution' => $startAfterCancel, 'meeting_status' => $meeting->fresh()->status, 'active_execution_sessions' => \App\Models\MeetingExecutionSession::where('meeting_id', $meeting->id)->where('status', 'IN_PROGRESS')->count()];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'CANCELLATION', 'لغو در برابر شروع جلسه.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$startFirst = attempt(fn () => app(StartMeetingExecution::class)->execute($owner, $meeting->fresh())->status);
$cancelAfterStart = attempt(fn () => app(ApplyMeetingCancellation::class)->execute($case->fresh(), $owner)->status);
$race['cancellation_vs_execution']['execution_first'] = ['execution' => $startFirst, 'cancellation' => $cancelAfterStart, 'meeting_status' => $meeting->fresh()->status, 'active_execution_sessions' => \App\Models\MeetingExecutionSession::where('meeting_id', $meeting->id)->where('status', 'IN_PROGRESS')->count()];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'POSTPONEMENT', 'تعویق در برابر تأیید تغییر زمان.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$reschedule = rescheduleFixture($meeting, $owner, $unit);
$scheduleFirst = attempt(fn () => app(ConfirmMeetingReschedule::class)($owner, $meeting->fresh(), $reschedule->fresh())->status);
$postponementAfterSchedule = attempt(fn () => app(ApplyMeetingPostponement::class)->execute($case->fresh(), $owner)->status);
$race['postponement_vs_flow07_confirmation']['flow07_first'] = ['flow07' => $scheduleFirst, 'postponement' => $postponementAfterSchedule, 'meeting_status' => $meeting->fresh()->status, 'schedule_version_count' => $meeting->fresh()->scheduleVersions()->count()];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'POSTPONEMENT', 'تعویق در برابر تأیید تغییر زمان.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$reschedule = rescheduleFixture($meeting, $owner, $unit);
$postponementFirst = attempt(fn () => app(ApplyMeetingPostponement::class)->execute($case->fresh(), $owner)->status);
$flow07AfterPostponement = attempt(fn () => app(ConfirmMeetingReschedule::class)($owner, $meeting->fresh(), $reschedule->fresh())->status);
$race['postponement_vs_flow07_confirmation']['postponement_first'] = ['postponement' => $postponementFirst, 'flow07' => $flow07AfterPostponement, 'meeting_status' => $meeting->fresh()->status, 'schedule_version_count' => $meeting->fresh()->scheduleVersions()->count()];

$meeting = fixture($owner, $unit, $employee);
$cancelCase = prepareCase($meeting, $owner, 'CANCELLATION', 'لغو پیش از بازگشایی رقابتی.', true);
$review = $cancelCase->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
app(ApplyMeetingCancellation::class)->execute($cancelCase, $owner);
$reopen = prepareCase($meeting->fresh(), $owner, 'REOPENING', 'بازگشایی رقابتی.', true);
$review = $reopen->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$race['concurrent_reopening_apply'] = ['workers' => [worker('reopen-a', fn () => app(ApplyMeetingReopening::class)->execute($reopen->fresh(), $owner)->status), worker('reopen-b', fn () => app(ApplyMeetingReopening::class)->execute($reopen->fresh(), $owner)->status)], 'meeting_status' => $meeting->fresh()->status];

$meeting = fixture($owner, $unit, $employee);
$postpone = prepareCase($meeting, $owner, 'POSTPONEMENT', 'تعویق پیش از resumption رقابتی.', true);
$review = $postpone->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
app(ApplyMeetingPostponement::class)->execute($postpone, $owner);
$resume = prepareCase($meeting->fresh(), $owner, 'RESUMPTION', 'بازگشت رقابتی به تقویم.', true, true);
$review = $resume->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$oldScheduleCount = $meeting->fresh()->scheduleVersions()->count();
$resumptionDate = now()->addDays(600 + random_int(0, 1000))->format('Y-m-d');
$race['concurrent_resumption'] = ['workers' => [worker('resume-a', fn () => app(ResumePostponedMeeting::class)->execute($resume->fresh(), $owner, ['meeting_date' => $resumptionDate, 'start_time' => '09:00', 'end_time' => '10:00', 'location' => 'FLOW13 RESUME A'])->status), worker('resume-b', fn () => app(ResumePostponedMeeting::class)->execute($resume->fresh(), $owner, ['meeting_date' => $resumptionDate, 'start_time' => '09:00', 'end_time' => '10:00', 'location' => 'FLOW13 RESUME B'])->status)], 'meeting_status' => $meeting->fresh()->status, 'schedule_version_count_before' => $oldScheduleCount, 'schedule_version_count_after' => $meeting->fresh()->scheduleVersions()->count()];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'CANCELLATION', 'لغو در برابر تأیید FLOW-08.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$participantCase = participantChangeFixture($meeting->fresh(), $owner, $unit, $replacementEmployee);
$beforeParticipantVersion = (int) $meeting->fresh()->current_participant_version_id;
$participantFirst = attempt(fn () => app(ConfirmMeetingParticipantChange::class)->execute($meeting->fresh(), $participantCase->fresh(), $director)->status);
$afterParticipantVersion = (int) $meeting->fresh()->current_participant_version_id;
$cancellationAfterParticipant = attempt(fn () => app(ApplyMeetingCancellation::class)->execute($case->fresh(), $owner)->status);
$race['cancellation_vs_participant_confirmation']['flow08_first'] = ['before_participant_version_id' => $beforeParticipantVersion, 'after_participant_version_id' => $afterParticipantVersion, 'meeting_status' => $meeting->fresh()->status, 'exception_case_status' => $case->fresh()->status, 'participant_confirmation' => $participantFirst, 'cancellation' => $cancellationAfterParticipant, 'loser_error_code' => $cancellationAfterParticipant['error']['message'] ?? null];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'CANCELLATION', 'لغو پیش از FLOW-08.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$participantCase = participantChangeFixture($meeting->fresh(), $owner, $unit, $replacementEmployee);
$beforeParticipantVersion = (int) $meeting->fresh()->current_participant_version_id;
$cancellationFirst = attempt(fn () => app(ApplyMeetingCancellation::class)->execute($case->fresh(), $owner)->status);
$afterParticipantVersion = (int) $meeting->fresh()->current_participant_version_id;
$participantAfterCancellation = attempt(fn () => app(ConfirmMeetingParticipantChange::class)->execute($meeting->fresh(), $participantCase->fresh(), $director)->status);
$race['cancellation_vs_participant_confirmation']['cancellation_first'] = ['before_participant_version_id' => $beforeParticipantVersion, 'after_participant_version_id' => $afterParticipantVersion, 'meeting_status' => $meeting->fresh()->status, 'exception_case_status' => $case->fresh()->status, 'cancellation' => $cancellationFirst, 'participant_confirmation' => $participantAfterCancellation, 'loser_error_code' => $participantAfterCancellation['error']['message'] ?? null, 'participant_version_created_after_cancellation' => $afterParticipantVersion !== $beforeParticipantVersion];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'CANCELLATION', 'لغو در برابر فعال‌سازی ترمینل.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
[$terminal, $assignment] = terminalAssignmentFixture($meeting->fresh(), $owner);
$activationFirst = attempt(fn () => app(ActivateTerminalMeetingSession::class)->execute($terminal->fresh(), $assignment->fresh())['session']->id);
$cancellationAfterActivation = attempt(fn () => app(ApplyMeetingCancellation::class)->execute($case->fresh(), $owner)->status);
$sessionId = $activationFirst['outcome'] ?? null;
$race['cancellation_vs_terminal_activation']['activation_first'] = ['assignment_initial_status' => 'ASSIGNED', 'activation' => $activationFirst, 'cancellation' => $cancellationAfterActivation, 'assignment_status' => $assignment->fresh()->status, 'session_id' => $sessionId, 'session_status' => $sessionId ? DB::table('terminal_meeting_sessions')->where('id', $sessionId)->value('status') : null, 'active_terminal_assignment_count' => MeetingTerminalAssignment::where('meeting_id', $meeting->id)->whereIn('status', ['ASSIGNED', 'ACTIVE'])->count(), 'active_terminal_session_count' => DB::table('terminal_meeting_sessions')->where('meeting_id', $meeting->id)->where('status', 'ACTIVE')->count()];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'CANCELLATION', 'لغو پیش از فعال‌سازی ترمینل.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
[$terminal, $assignment] = terminalAssignmentFixture($meeting->fresh(), $owner);
$cancellationFirst = attempt(fn () => app(ApplyMeetingCancellation::class)->execute($case->fresh(), $owner)->status);
$activationAfterCancellation = attempt(fn () => app(ActivateTerminalMeetingSession::class)->execute($terminal->fresh(), $assignment->fresh())['session']->id);
$race['cancellation_vs_terminal_activation']['cancellation_first'] = ['assignment_initial_status' => 'ASSIGNED', 'cancellation' => $cancellationFirst, 'activation' => $activationAfterCancellation, 'assignment_status' => $assignment->fresh()->status, 'session_status' => null, 'active_terminal_assignment_count' => MeetingTerminalAssignment::where('meeting_id', $meeting->id)->whereIn('status', ['ASSIGNED', 'ACTIVE'])->count(), 'active_terminal_session_count' => DB::table('terminal_meeting_sessions')->where('meeting_id', $meeting->id)->where('status', 'ACTIVE')->count(), 'loser_error_code' => $activationAfterCancellation['error']['message'] ?? null];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'CANCELLATION', 'لغو پس از حضور ثبت‌شده.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$attendanceFirst = attempt(fn () => app(RecordManualAttendance::class)->execute($owner, $meeting->fresh(), $employee->id, 'حضور پیش از لغو.')->id);
$cancellationAfterAttendance = attempt(fn () => app(ApplyMeetingCancellation::class)->execute($case->fresh(), $owner)->status);
$after = $meeting->fresh(['attendanceRecords.invalidation']);
$race['cancellation_vs_attendance_orderings']['attendance_first'] = ['attendance' => $attendanceFirst, 'cancellation' => $cancellationAfterAttendance, 'raw_count' => $after->attendanceRecords->count(), 'invalidation_count' => $after->attendanceRecords->filter(fn ($row) => $row->invalidation !== null)->count(), 'official_count' => $after->attendanceRecords()->whereDoesntHave('invalidation')->count()];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'CANCELLATION', 'لغو پیش از حضور.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$cancellationFirst = attempt(fn () => app(ApplyMeetingCancellation::class)->execute($case->fresh(), $owner)->status);
$attendanceAfterCancellation = attempt(fn () => app(RecordManualAttendance::class)->execute($owner, $meeting->fresh(), $employee->id, 'حضور پس از لغو.')->id);
$after = $meeting->fresh(['attendanceRecords.invalidation']);
$race['cancellation_vs_attendance_orderings']['cancellation_first'] = ['cancellation' => $cancellationFirst, 'attendance' => $attendanceAfterCancellation, 'raw_count' => $after->attendanceRecords->count(), 'invalidation_count' => $after->attendanceRecords->filter(fn ($row) => $row->invalidation !== null)->count(), 'official_count' => $after->attendanceRecords()->whereDoesntHave('invalidation')->count()];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'POSTPONEMENT', 'تعویق پس از حضور ثبت‌شده.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$attendanceFirst = attempt(fn () => app(RecordManualAttendance::class)->execute($owner, $meeting->fresh(), $employee->id, 'حضور پیش از تعویق.')->id);
$postponementAfterAttendance = attempt(fn () => app(ApplyMeetingPostponement::class)->execute($case->fresh(), $owner)->status);
$after = $meeting->fresh(['attendanceRecords.invalidation']);
$race['postponement_vs_attendance_orderings']['attendance_first'] = ['attendance' => $attendanceFirst, 'postponement' => $postponementAfterAttendance, 'raw_count' => $after->attendanceRecords->count(), 'invalidation_count' => $after->attendanceRecords->filter(fn ($row) => $row->invalidation !== null)->count(), 'official_count' => $after->attendanceRecords()->whereDoesntHave('invalidation')->count()];

$meeting = fixture($owner, $unit, $employee);
$case = prepareCase($meeting, $owner, 'POSTPONEMENT', 'تعویق پیش از حضور.', true);
$review = $case->currentVersion->reviews->first();
app(ApproveMeetingExceptionReview::class)->execute($review, $director);
$postponementFirst = attempt(fn () => app(ApplyMeetingPostponement::class)->execute($case->fresh(), $owner)->status);
$attendanceAfterPostponement = attempt(fn () => app(RecordManualAttendance::class)->execute($owner, $meeting->fresh(), $employee->id, 'حضور پس از تعویق.')->id);
$after = $meeting->fresh(['attendanceRecords.invalidation']);
$race['postponement_vs_attendance_orderings']['postponement_first'] = ['postponement' => $postponementFirst, 'attendance' => $attendanceAfterPostponement, 'raw_count' => $after->attendanceRecords->count(), 'invalidation_count' => $after->attendanceRecords->filter(fn ($row) => $row->invalidation !== null)->count(), 'official_count' => $after->attendanceRecords()->whereDoesntHave('invalidation')->count()];

$allWorkers = collect($race)->flatMap(fn (array $group): array => $group['workers'] ?? [])->values();
$oneWinnerOneLoser = fn (array $workers): bool => count(array_filter($workers, fn (array $worker): bool => $worker['ok'])) === 1 && count(array_filter($workers, fn (array $worker): bool => ! $worker['ok'])) === 1;
$errorContains = fn (array $result, string $text): bool => ! $result['ok'] && str_contains((string) ($result['error']['message'] ?? ''), $text);
$race['pass'] = $race['active_case_creation']['active_case_count'] === 1
    && $oneWinnerOneLoser($race['active_case_creation']['workers'])
    && $oneWinnerOneLoser($race['duplicate_application']['workers'])
    && $race['duplicate_application']['case_status'] === 'APPLIED'
    && $race['duplicate_application']['meeting_status'] === 'CANCELLED'
    && $oneWinnerOneLoser($race['approval_vs_correction']['workers'])
    && in_array($race['approval_vs_correction']['case_status'], ['READY_TO_APPLY', 'RETURNED_FOR_CORRECTION'], true)
    && $oneWinnerOneLoser($race['cancellation_vs_attendance']['workers'])
    && $race['cancellation_vs_attendance']['meeting_status'] === 'CANCELLED'
    && $race['cancellation_vs_attendance']['official_attendance_rows'] === 0
    && $race['cancellation_vs_execution']['cancellation_first']['meeting_status'] === 'CANCELLED'
    && $race['cancellation_vs_execution']['cancellation_first']['active_execution_sessions'] === 0
    && $errorContains($race['cancellation_vs_execution']['cancellation_first']['execution'], 'MEETING_NOT_EXECUTABLE')
    && $race['cancellation_vs_execution']['execution_first']['meeting_status'] === 'IN_PROGRESS'
    && $race['cancellation_vs_execution']['execution_first']['active_execution_sessions'] === 1
    && $errorContains($race['cancellation_vs_execution']['execution_first']['cancellation'], 'MEETING_EXECUTION_ALREADY_STARTED')
    && $race['postponement_vs_flow07_confirmation']['flow07_first']['meeting_status'] === 'SCHEDULED'
    && $race['postponement_vs_flow07_confirmation']['flow07_first']['schedule_version_count'] === 2
    && $errorContains($race['postponement_vs_flow07_confirmation']['flow07_first']['postponement'], 'MEETING_EXCEPTION_BASELINE_CHANGED')
    && $race['postponement_vs_flow07_confirmation']['postponement_first']['meeting_status'] === 'POSTPONED'
    && $errorContains($race['postponement_vs_flow07_confirmation']['postponement_first']['flow07'], 'MEETING_NOT_RESCHEDULABLE')
    && $oneWinnerOneLoser($race['concurrent_reopening_apply']['workers'])
    && $race['concurrent_reopening_apply']['meeting_status'] === 'POSTPONED'
    && $oneWinnerOneLoser($race['concurrent_resumption']['workers'])
    && $race['concurrent_resumption']['meeting_status'] === 'SCHEDULED'
    && $race['concurrent_resumption']['schedule_version_count_after'] === $race['concurrent_resumption']['schedule_version_count_before'] + 1
    && ($errorContains($race['concurrent_resumption']['workers'][0], 'MEETING_EXCEPTION_ALREADY_APPLIED') || $errorContains($race['concurrent_resumption']['workers'][1], 'MEETING_EXCEPTION_ALREADY_APPLIED'))
    && $race['cancellation_vs_participant_confirmation']['flow08_first']['participant_confirmation']['ok']
    && $race['cancellation_vs_participant_confirmation']['flow08_first']['after_participant_version_id'] !== $race['cancellation_vs_participant_confirmation']['flow08_first']['before_participant_version_id']
    && $errorContains($race['cancellation_vs_participant_confirmation']['flow08_first']['cancellation'], 'MEETING_EXCEPTION_BASELINE_CHANGED')
    && $race['cancellation_vs_participant_confirmation']['cancellation_first']['meeting_status'] === 'CANCELLED'
    && $race['cancellation_vs_participant_confirmation']['cancellation_first']['after_participant_version_id'] === $race['cancellation_vs_participant_confirmation']['cancellation_first']['before_participant_version_id']
    && $errorContains($race['cancellation_vs_participant_confirmation']['cancellation_first']['participant_confirmation'], 'MEETING_NOT_PARTICIPANT_EDITABLE')
    && $race['cancellation_vs_terminal_activation']['activation_first']['activation']['ok']
    && $race['cancellation_vs_terminal_activation']['activation_first']['cancellation']['ok']
    && $race['cancellation_vs_terminal_activation']['activation_first']['assignment_status'] === 'REVOKED'
    && $race['cancellation_vs_terminal_activation']['activation_first']['session_status'] === 'REVOKED'
    && $race['cancellation_vs_terminal_activation']['activation_first']['active_terminal_assignment_count'] === 0
    && $race['cancellation_vs_terminal_activation']['activation_first']['active_terminal_session_count'] === 0
    && $race['cancellation_vs_terminal_activation']['cancellation_first']['cancellation']['ok']
    && $errorContains($race['cancellation_vs_terminal_activation']['cancellation_first']['activation'], 'MEETING_NOT_SCHEDULED')
    && $race['cancellation_vs_terminal_activation']['cancellation_first']['assignment_status'] === 'REVOKED'
    && $race['cancellation_vs_terminal_activation']['cancellation_first']['active_terminal_assignment_count'] === 0
    && $race['cancellation_vs_terminal_activation']['cancellation_first']['active_terminal_session_count'] === 0
    && $race['cancellation_vs_attendance_orderings']['attendance_first']['raw_count'] === 1
    && $race['cancellation_vs_attendance_orderings']['attendance_first']['invalidation_count'] === 1
    && $race['cancellation_vs_attendance_orderings']['attendance_first']['official_count'] === 0
    && $race['cancellation_vs_attendance_orderings']['cancellation_first']['raw_count'] === 0
    && $race['cancellation_vs_attendance_orderings']['cancellation_first']['invalidation_count'] === 0
    && $race['cancellation_vs_attendance_orderings']['cancellation_first']['official_count'] === 0
    && $race['postponement_vs_attendance_orderings']['attendance_first']['raw_count'] === 1
    && $race['postponement_vs_attendance_orderings']['attendance_first']['invalidation_count'] === 1
    && $race['postponement_vs_attendance_orderings']['attendance_first']['official_count'] === 0
    && $race['postponement_vs_attendance_orderings']['postponement_first']['raw_count'] === 0
    && $race['postponement_vs_attendance_orderings']['postponement_first']['invalidation_count'] === 0
    && $race['postponement_vs_attendance_orderings']['postponement_first']['official_count'] === 0;
$race['race_group_count'] = count(array_filter(array_keys($race), fn (string $key): bool => !in_array($key, ['pass', 'race_group_count'], true)));
echo json_encode($race, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
