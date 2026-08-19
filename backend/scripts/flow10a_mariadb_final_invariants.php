<?php

declare(strict_types=1);

use App\Actions\Attendance\RecordFingerprintAttendance;
use App\Actions\Attendance\RecordManualAttendance;
use App\Actions\Biometrics\CancelBiometricEnrollment;
use App\Actions\Biometrics\CompleteBiometricEnrollment;
use App\Actions\Biometrics\ReEnrollFingerprint;
use App\Actions\Biometrics\RevokeFingerprintTemplate;
use App\Actions\Biometrics\StartBiometricEnrollment;
use App\Actions\Biometrics\SubmitFingerprintTemplate;
use App\Actions\Meetings\AssignTerminalToMeeting;
use App\Actions\Terminals\CloseTerminalMeetingSession;
use App\Actions\Terminals\RegisterTerminal;
use App\Models\BiometricEnrollmentSession;
use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use App\Models\Meeting;
use App\Models\MeetingAttendanceRecord;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Support\BiometricCandidateSetBuilder;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
$coordinator = User::query()->where('email', 'tech.coordinator@example.test')->firstOrFail();
$unit = App\Models\OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
$employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
$secondEmployee = Employee::query()->where('email', 'employee.a@example.test')->firstOrFail();

TerminalMeetingSession::query()->where('status', 'ACTIVE')->get()->each(function (TerminalMeetingSession $activeSession): void {
    app(CloseTerminalMeetingSession::class)->execute($activeSession, 'FLOW10A canonical pre-fixture cleanup');
});
BiometricEnrollmentSession::query()->active()->get()->each(function (BiometricEnrollmentSession $activeEnrollment) use ($actor): void {
    if ($activeEnrollment->isExpired()) {
        $activeEnrollment->forceFill(['status' => BiometricEnrollmentSession::EXPIRED])->saveQuietly();
        return;
    }
    app(CancelBiometricEnrollment::class)->execute($actor, $activeEnrollment, 'FLOW10A canonical pre-fixture cleanup');
});
EmployeeBiometricTemplate::query()->where('status', EmployeeBiometricTemplate::ACTIVE)->get()->each(function (EmployeeBiometricTemplate $activeTemplate) use ($actor): void {
    app(RevokeFingerprintTemplate::class)->execute($actor, $activeTemplate, 'FLOW10A canonical pre-fixture cleanup');
});

$terminal = app(RegisterTerminal::class)->execute($actor, [
    'organization_id' => $unit->organization_id,
    'code' => 'FLOW10A-CANONICAL-'.Str::upper(Str::random(8)),
    'name' => 'FLOW-10A Canonical Invariant Terminal',
    'status' => 'ACTIVE',
]);

$request = MeetingRequest::create([
    'request_number' => 'FLOW10A-CANONICAL-'.Str::upper(Str::random(8)),
    'flow_type' => 'MULTI_DIRECTORATE',
    'requesting_unit_id' => $unit->id,
    'created_by' => $actor->id,
    'subject' => 'FLOW-10A Canonical Invariant Meeting',
    'purpose' => 'Canonical final invariant evidence',
    'status' => 'CONVERTED_TO_MEETING',
    'coordinator_id' => $coordinator->id,
]);
$meeting = Meeting::create([
    'meeting_request_id' => $request->id,
    'meeting_number' => 'FLOW10A-CANONICAL-M-'.Str::upper(Str::random(8)),
    'title' => $request->subject,
    'organizing_unit_id' => $unit->id,
    'coordinator_id' => $coordinator->id,
    'chairperson_employee_id' => $employee->id,
    'meeting_date' => now()->addDays(30)->format('Y-m-d'),
    'start_time' => '10:00',
    'end_time' => '11:00',
    'location' => 'FLOW10A-CANONICAL',
    'status' => 'SCHEDULED',
]);
MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $secondEmployee->id, 'organizational_unit_id' => $secondEmployee->organizational_unit_id, 'role' => 'PARTICIPATING']);
$schedule = app(ScheduleVersionService::class)->createNext($meeting, [
    'meeting_date' => $meeting->meeting_date->format('Y-m-d'),
    'start_time' => '10:00',
    'end_time' => '11:00',
    'location' => 'FLOW10A-CANONICAL',
], $actor->id);
app(ScheduleVersionService::class)->applyToMeeting($meeting, $schedule);
app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
$meeting = $meeting->fresh();
$assignment = app(AssignTerminalToMeeting::class)->execute($coordinator, $meeting, $terminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(40)->toIso8601String());

$enrollment = app(StartBiometricEnrollment::class)->execute($actor, $employee, $terminal, 2);
$submit = app(SubmitFingerprintTemplate::class);
$submit->execute($actor, $enrollment, 'RIGHT_INDEX', 'FAKE', 'SIMULATOR-V1', 'SIMULATOR-V1', 'FLOW10A_CANONICAL_RIGHT_V1', 95.0, true);
$submit->execute($actor, $enrollment, 'LEFT_INDEX', 'FAKE', 'SIMULATOR-V1', 'SIMULATOR-V1', 'FLOW10A_CANONICAL_LEFT_V1', 95.0, true);
app(CompleteBiometricEnrollment::class)->execute($actor, $enrollment);
$reenrollment = app(StartBiometricEnrollment::class)->execute($actor, $employee, $terminal, 2);
app(ReEnrollFingerprint::class)->execute($actor, $reenrollment, 'RIGHT_INDEX', 'FAKE', 'SIMULATOR-V1', 'SIMULATOR-V1', 'FLOW10A_CANONICAL_RIGHT_V2', 96.0, true);
app(CompleteBiometricEnrollment::class)->execute($actor, $reenrollment);

$session = TerminalMeetingSession::create([
    'uuid' => (string) Str::uuid(),
    'assignment_id' => $assignment->id,
    'terminal_id' => $terminal->id,
    'meeting_id' => $meeting->id,
    'schedule_version_id' => $meeting->current_schedule_version_id,
    'participant_version_id' => $meeting->current_participant_version_id,
    'status' => 'ACTIVE',
    'session_token_hash' => hash('sha256', Str::random(48)),
    'activated_at' => now(),
    'expires_at' => now()->addHour(),
]);
$candidates = app(BiometricCandidateSetBuilder::class)->build($session);
$activeRight = EmployeeBiometricTemplate::query()->where('employee_id', $employee->id)->where('finger_position', 'RIGHT_INDEX')->where('status', EmployeeBiometricTemplate::ACTIVE)->firstOrFail();
app(RecordFingerprintAttendance::class)->execute($session, [
    'client_event_uuid' => (string) Str::uuid(),
    'employee_id' => $employee->id,
    'matched_template_uuid' => $activeRight->uuid,
    'finger_position' => 'RIGHT_INDEX',
    'matcher_vendor' => 'FAKE',
    'matcher_algorithm_version' => 'SIMULATOR-V1',
    'match_score' => 99.0,
    'threshold_reference' => 'FAKE_ACCEPTED',
    'candidate_set_checksum' => $candidates['candidate_set_checksum'],
]);
app(RecordManualAttendance::class)->execute($coordinator, $meeting, $secondEmployee->id, 'FLOW10A canonical manual override');

$meeting = $meeting->fresh(['request', 'currentScheduleVersion', 'currentParticipantVersion.members']);
$templates = EmployeeBiometricTemplate::query()->where('employee_id', $employee->id)->orderBy('finger_position')->orderBy('version_number')->get();
$attendance = MeetingAttendanceRecord::query()->where('meeting_id', $meeting->id)->orderBy('employee_id')->get(['employee_id', 'attendance_method', 'participant_version_id']);
$duplicateAttendanceCount = DB::table('meeting_attendance_records')->select('meeting_id', 'employee_id')->groupBy('meeting_id', 'employee_id')->havingRaw('COUNT(*) > 1')->count();
$requestMeetingDuplicateCount = DB::table('meetings')->select('meeting_request_id')->groupBy('meeting_request_id')->havingRaw('COUNT(*) > 1')->count();

$artifact = [
    'artifact' => 'FLOW10A_FINAL_MARIADB_INVARIANTS',
    'generated_at' => now()->toIso8601String(),
    'database' => DB::connection()->getDatabaseName(),
    'canonical_fixture_snapshot' => [
        'employee_id' => $employee->id,
        'employee_email' => $employee->email,
        'biometric_status' => 'ENROLLED',
        'active_finger_count' => $templates->where('status', EmployeeBiometricTemplate::ACTIVE)->unique('finger_position')->count(),
        'fingers' => $templates->map(fn (EmployeeBiometricTemplate $template): array => [
            'finger_position' => $template->finger_position,
            'version_number' => (int) $template->version_number,
            'status' => $template->status,
            'template_checksum' => $template->template_checksum,
        ])->values()->all(),
        'human_template_exposure' => $templates->contains(fn (EmployeeBiometricTemplate $template): bool => array_key_exists('template_data', $template->toArray())),
    ],
    'canonical_meeting_snapshot' => [
        'meeting_id' => $meeting->id,
        'meeting_request_id' => $meeting->meeting_request_id,
        'request_status' => $meeting->request->status,
        'meeting_status' => $meeting->status,
        'schedule_version' => ['id' => $meeting->current_schedule_version_id, 'version_number' => $meeting->currentScheduleVersion?->version_number],
        'participant_version' => ['id' => $meeting->current_participant_version_id, 'version_number' => $meeting->currentParticipantVersion?->version_number],
        'terminal_id' => $terminal->id,
        'terminal_session_id' => $session->id,
        'candidate_count' => count($candidates['candidates']),
        'biometric_ready_participant_count' => $candidates['biometric_ready'],
        'without_biometrics' => $candidates['without_biometrics'],
        'attendance_employee_ids' => $attendance->pluck('employee_id')->map(fn ($id): int => (int) $id)->values()->all(),
        'attendance_methods' => $attendance->pluck('attendance_method')->values()->all(),
    ],
    'duplicate_attendance_count' => $duplicateAttendanceCount,
    'request_meeting_duplicate_count' => $requestMeetingDuplicateCount,
    'plaintext_probe_columns_present' => collect(['probe_data', 'raw_image', 'sensor_frame', 'fingerprint_image'])->filter(fn (string $column): bool => Schema::hasColumn('meeting_attendance_records', $column) || Schema::hasColumn('employee_biometric_templates', $column))->values()->all(),
];

TerminalMeetingSession::query()->where('status', 'ACTIVE')->get()->each(function (TerminalMeetingSession $activeSession): void {
    app(CloseTerminalMeetingSession::class)->execute($activeSession, 'FLOW10A final canonical cleanup');
});
BiometricEnrollmentSession::query()->active()->get()->each(function (BiometricEnrollmentSession $activeEnrollment) use ($actor): void {
    if ($activeEnrollment->isExpired()) {
        $activeEnrollment->forceFill(['status' => BiometricEnrollmentSession::EXPIRED])->saveQuietly();
        return;
    }
    app(CancelBiometricEnrollment::class)->execute($actor, $activeEnrollment, 'FLOW10A final canonical cleanup');
});
EmployeeBiometricTemplate::query()->where('status', EmployeeBiometricTemplate::ACTIVE)->get()->each(function (EmployeeBiometricTemplate $activeTemplate) use ($actor): void {
    app(RevokeFingerprintTemplate::class)->execute($actor, $activeTemplate, 'FLOW10A final canonical cleanup');
});

$artifact['post_cleanup_invariants'] = [
    'active_terminal_sessions' => TerminalMeetingSession::query()->where('status', 'ACTIVE')->count(),
    'active_enrollment_sessions' => BiometricEnrollmentSession::query()->active()->count(),
    'active_terminals' => Terminal::query()->where('status', 'ACTIVE')->count(),
    'active_template_rows' => EmployeeBiometricTemplate::query()->where('status', EmployeeBiometricTemplate::ACTIVE)->count(),
    'revoked_or_superseded_template_history' => EmployeeBiometricTemplate::query()->whereIn('status', [EmployeeBiometricTemplate::REVOKED, EmployeeBiometricTemplate::SUPERSEDED])->count(),
    'attendance_rows' => MeetingAttendanceRecord::query()->count(),
    'duplicate_meeting_employee_identities' => DB::table('meeting_attendance_records')->select('meeting_id', 'employee_id')->groupBy('meeting_id', 'employee_id')->havingRaw('COUNT(*) > 1')->count(),
    'plaintext_probe_columns_present' => $artifact['plaintext_probe_columns_present'],
    'meeting_status_counts' => Meeting::query()->select('status', DB::raw('COUNT(*) as total'))->groupBy('status')->pluck('total', 'status')->all(),
];

$path = dirname(__DIR__, 2).'/flow10a_final_mariadb_invariants.json';
file_put_contents($path, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
