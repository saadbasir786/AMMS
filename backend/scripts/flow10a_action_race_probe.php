<?php

declare(strict_types=1);

use App\Actions\Attendance\RecordFingerprintAttendance;
use App\Actions\Biometrics\ReEnrollFingerprint;
use App\Actions\Biometrics\StartBiometricEnrollment;
use App\Actions\Biometrics\RevokeFingerprintTemplate;
use App\Actions\Meetings\AssignTerminalToMeeting;
use App\Actions\Terminals\RegisterTerminal;
use App\Models\BiometricEnrollmentSession;
use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingParticipantChangeCase;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Support\BiometricCandidateSetBuilder;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$reconnect = static function (): void {
    DB::purge('mysql');
    DB::reconnect('mysql');
};

$runTwo = static function (callable $worker) use ($reconnect): array {
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        [$read, $write] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('fork failed');
        if ($pid === 0) {
            fclose($read);
            try {
                $reconnect();
                $result = $worker($i);
                fwrite($write, json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE));
            } catch (Throwable $e) {
                $response = method_exists($e, 'getResponse') ? $e->getResponse() : null;
                fwrite($write, json_encode(['ok' => false, 'class' => $e::class, 'message' => $e->getMessage(), 'code' => $response?->getData(true)['code'] ?? null, 'status' => $response?->getStatusCode() ?? null], JSON_UNESCAPED_UNICODE));
            }
            fclose($write);
            exit(0);
        }
        fclose($write);
        $children[] = [$pid, $read];
    }
    $results = [];
    foreach ($children as [$pid, $read]) {
        $results[] = json_decode(stream_get_contents($read), true, 512, JSON_THROW_ON_ERROR);
        fclose($read);
        pcntl_waitpid($pid, $status);
    }
    return $results;
};

$actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
$admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
$unit = App\Models\OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
$employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();

$fixture = static function (string $tag) use ($actor, $admin, $unit, $employee): array {
    $request = MeetingRequest::create([
        'request_number' => 'FLOW10A-RACE-'.$tag.'-'.Str::random(8),
        'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $actor->id,
        'subject' => 'FLOW-10A race '.$tag, 'purpose' => 'CONCURRENCY', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $actor->id,
    ]);
    $meeting = Meeting::create([
        'meeting_request_id' => $request->id, 'meeting_number' => 'FLOW10A-RACE-M-'.$tag.'-'.Str::random(6),
        'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $actor->id,
        'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->addDays(30)->format('Y-m-d'),
        'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW10A-RACE', 'status' => 'SCHEDULED',
    ]);
    MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
    MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
    $scheduleService = app(ScheduleVersionService::class);
    $version = $scheduleService->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW10A-RACE'], $actor->id);
    $scheduleService->applyToMeeting($meeting, $version);
    app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
    $meeting = $meeting->fresh();
    $terminal = app(RegisterTerminal::class)->execute($admin, ['organization_id' => $unit->organization_id, 'code' => 'FLOW10A-RACE-T-'.$tag.'-'.Str::random(6), 'name' => 'FLOW10A Race Terminal', 'status' => 'ACTIVE']);
    $assignment = app(AssignTerminalToMeeting::class)->execute($actor, $meeting, $terminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(40)->toIso8601String());
    $session = TerminalMeetingSession::create([
        'uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'terminal_id' => $terminal->id, 'meeting_id' => $meeting->id,
        'schedule_version_id' => $meeting->current_schedule_version_id, 'participant_version_id' => $meeting->current_participant_version_id,
        'status' => 'ACTIVE', 'session_token_hash' => hash('sha256', Str::random(32)), 'activated_at' => now(), 'expires_at' => now()->addHour(),
    ]);
    EmployeeBiometricTemplate::query()->where('employee_id', $employee->id)->where('finger_position', 'RIGHT_INDEX')->where('status', EmployeeBiometricTemplate::ACTIVE)->update(['status' => EmployeeBiometricTemplate::REVOKED, 'revoked_at' => now(), 'revoke_reason' => 'isolated race fixture']);
    $nextVersion = ((int) EmployeeBiometricTemplate::query()->where('employee_id', $employee->id)->where('finger_position', 'RIGHT_INDEX')->max('version_number')) + 1;
    $bytes = 'FLOW10A_RACE_TEMPLATE_'.$tag;
    $template = EmployeeBiometricTemplate::create([
        'uuid' => (string) Str::uuid(), 'organization_id' => $terminal->organization_id, 'employee_id' => $employee->id,
        'modality' => 'FINGERPRINT', 'finger_position' => 'RIGHT_INDEX', 'template_vendor' => 'FAKE', 'template_format' => 'SIMULATOR-V1',
        'algorithm_version' => 'SIMULATOR-V1', 'template_data' => $bytes, 'template_checksum' => hash('sha256', $bytes), 'quality_score' => 95,
        'version_number' => $nextVersion, 'status' => 'ACTIVE', 'source_terminal_id' => $terminal->id, 'enrolled_by_user_id' => $actor->id, 'enrolled_at' => now(),
    ]);
    return compact('meeting', 'terminal', 'assignment', 'session', 'template', 'actor', 'employee', 'unit');
};

$attendanceData = static function (array $f, string $checksum): array {
    return ['client_event_uuid' => (string) Str::uuid(), 'employee_id' => $f['employee']->id, 'matched_template_uuid' => $f['template']->uuid, 'finger_position' => 'RIGHT_INDEX', 'matcher_vendor' => 'FAKE', 'matcher_algorithm_version' => 'SIMULATOR-V1', 'match_score' => 99.0, 'threshold_reference' => 'FAKE_ACCEPTED', 'candidate_set_checksum' => $checksum];
};

$results = [];

$enrollTerminal = app(RegisterTerminal::class)->execute($admin, ['organization_id' => $unit->organization_id, 'code' => 'FLOW10A-RACE-ENROLL-'.Str::random(6), 'name' => 'FLOW10A Enrollment Race Terminal', 'status' => 'ACTIVE']);
$results['concurrent_enrollment_same_employee'] = $runTwo(static function (int $worker) use ($actor, $employee, $enrollTerminal): string {
    usleep($worker === 1 ? 30000 : 0);
    app(StartBiometricEnrollment::class)->execute($actor, $employee, $enrollTerminal, 2);
    return 'ENROLLMENT_COMMITTED';
});
$results['concurrent_enrollment_final_active_sessions'] = BiometricEnrollmentSession::query()->where('employee_id', $employee->id)->where('status', 'IN_PROGRESS')->orWhere(function ($q) use ($employee): void { $q->where('employee_id', $employee->id)->where('status', 'PENDING_CAPTURE'); })->count();

$f = $fixture('ATT');
$checksum = app(BiometricCandidateSetBuilder::class)->build($f['session'])['candidate_set_checksum'];
$results['two_terminal_attendance_submissions'] = $runTwo(static function (int $worker) use ($f, $checksum, $attendanceData): string {
    usleep($worker === 1 ? 40000 : 0);
    app(RecordFingerprintAttendance::class)->execute(TerminalMeetingSession::findOrFail($f['session']->id), $attendanceData($f, $checksum));
    return 'ATTENDANCE_COMMITTED';
});
$results['two_terminal_attendance_final_rows'] = DB::table('meeting_attendance_records')->where('meeting_id', $f['meeting']->id)->where('employee_id', $f['employee']->id)->count();

$f = $fixture('REV');
$enrollment = BiometricEnrollmentSession::create(['uuid' => (string) Str::uuid(), 'organization_id' => $f['terminal']->organization_id, 'employee_id' => $f['employee']->id, 'terminal_id' => $f['terminal']->id, 'started_by_user_id' => $f['actor']->id, 'status' => 'IN_PROGRESS', 'required_finger_count' => 2, 'started_at' => now(), 'expires_at' => now()->addHour()]);
$results['reenrollment_vs_revocation'] = $runTwo(static function (int $worker) use ($f, $enrollment): string {
    DB::beginTransaction();
    try {
        if ($worker === 0) {
            app(ReEnrollFingerprint::class)->execute($f['actor'], BiometricEnrollmentSession::findOrFail($enrollment->id), 'RIGHT_INDEX', 'FAKE', 'SIMULATOR-V1', 'SIMULATOR-V1', 'FLOW10A_REENROLLED', 96.0, true);
            usleep(120000);
            DB::commit();
            return 'REENROLL_COMMITTED';
        }
        usleep(30000);
        app(RevokeFingerprintTemplate::class)->execute($f['actor'], EmployeeBiometricTemplate::findOrFail($f['template']->id), 'race revoke');
        DB::commit();
        return 'REVOKE_COMMITTED';
    } catch (Throwable $e) {
        DB::rollBack();
        throw $e;
    }
});
$results['reenrollment_vs_revocation_active_rows'] = EmployeeBiometricTemplate::query()->where('employee_id', $f['employee']->id)->where('finger_position', 'RIGHT_INDEX')->where('status', 'ACTIVE')->count();

$f = $fixture('SCHED');
$checksum = app(BiometricCandidateSetBuilder::class)->build($f['session'])['candidate_set_checksum'];
$results['flow07_schedule_vs_attendance'] = $runTwo(static function (int $worker) use ($f, $checksum, $attendanceData): string {
    if ($worker === 0) {
        DB::transaction(function () use ($f): void {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($f['meeting']->id);
            $next = app(ScheduleVersionService::class)->createNext($meeting, ['meeting_date' => now()->addDays(31)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW10A-SCHEDULE-RACE'], $f['actor']->id);
            app(ScheduleVersionService::class)->applyToMeeting($meeting, $next);
            usleep(60000);
        });
        return 'SCHEDULE_CHANGED';
    }
    usleep(140000);
    app(RecordFingerprintAttendance::class)->execute(TerminalMeetingSession::findOrFail($f['session']->id), $attendanceData($f, $checksum));
    return 'ATTENDANCE_COMMITTED';
});
$results['flow07_schedule_final_session_stale'] = (int) (DB::table('terminal_meeting_sessions as s')->join('meetings as m', 'm.id', '=', 's.meeting_id')->where('s.id', $f['session']->id)->whereColumn('s.schedule_version_id', '!=', 'm.current_schedule_version_id')->count() > 0);

$f = $fixture('PART');
$checksum = app(BiometricCandidateSetBuilder::class)->build($f['session'])['candidate_set_checksum'];
$changeCase = MeetingParticipantChangeCase::create(['meeting_id' => $f['meeting']->id, 'owner_type' => 'UNIT', 'owner_unit_id' => $f['unit']->id, 'baseline_participant_version_id' => $f['meeting']->current_participant_version_id, 'baseline_schedule_version_id' => $f['meeting']->current_schedule_version_id, 'initiated_by_user_id' => $f['actor']->id, 'reason' => 'FLOW10A participant race', 'status' => 'CONFIRMED', 'confirmed_at' => now()]);
$results['flow08_participant_vs_attendance'] = $runTwo(static function (int $worker) use ($f, $checksum, $attendanceData, $changeCase): string {
    if ($worker === 0) {
        DB::transaction(function () use ($f, $changeCase): void {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($f['meeting']->id);
            $members = $meeting->currentParticipantVersion->members->map(fn ($m): array => ['employee_id' => $m->employee_id, 'organizational_unit_id' => $m->organizational_unit_id, 'participant_role' => $m->participant_role, 'owner_type' => $m->owner_type, 'owner_unit_id' => $m->owner_unit_id, 'is_mandatory' => $m->is_mandatory])->all();
            app(ParticipantVersionService::class)->createConfirmedVersion($meeting, $changeCase, $members, $f['actor']->id);
            usleep(60000);
        });
        return 'PARTICIPANTS_CHANGED';
    }
    usleep(140000);
    app(RecordFingerprintAttendance::class)->execute(TerminalMeetingSession::findOrFail($f['session']->id), $attendanceData($f, $checksum));
    return 'ATTENDANCE_COMMITTED';
});

$f = $fixture('TREV');
$checksum = app(BiometricCandidateSetBuilder::class)->build($f['session'])['candidate_set_checksum'];
$results['template_revocation_vs_attendance'] = $runTwo(static function (int $worker) use ($f, $checksum, $attendanceData): string {
    if ($worker === 0) {
        DB::beginTransaction();
        app(RevokeFingerprintTemplate::class)->execute($f['actor'], EmployeeBiometricTemplate::findOrFail($f['template']->id), 'race revoke before attendance');
        usleep(120000);
        DB::commit();
        return 'REVOKE_COMMITTED';
    }
    usleep(30000);
    app(RecordFingerprintAttendance::class)->execute(TerminalMeetingSession::findOrFail($f['session']->id), $attendanceData($f, $checksum));
    return 'ATTENDANCE_COMMITTED';
});
$results['template_revocation_vs_attendance_final_status'] = DB::table('employee_biometric_templates')->where('id', $f['template']->id)->value('status');

$results['status'] = 'ACTION_LEVEL_RACE_PROBE_COMPLETED';
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
