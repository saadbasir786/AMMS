<?php

declare(strict_types=1);

use App\Actions\Meetings\EndMeetingExecution;
use App\Actions\Meetings\StartMeetingExecution;
use App\Actions\Minutes\AcknowledgeMeetingMinutes;
use App\Actions\Minutes\ApproveHighAuthorityMeetingMinutes;
use App\Actions\Minutes\CreateMeetingMinutesDraft;
use App\Actions\Minutes\FinalizeMeetingMinutes;
use App\Actions\Minutes\GenerateFinalMeetingMinutesDocument;
use App\Actions\Minutes\SubmitMeetingMinutesVersion;
use App\Actions\Minutes\UpdateMeetingMinutesDraft;
use App\Actions\Terminals\CloseTerminalMeetingSession;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAttendanceRecord;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingExecutionSession;
use App\Models\MeetingMinuteDocument;
use App\Models\MeetingMinutes;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$coordinator = User::query()->where('email', 'tech.coordinator@example.test')->firstOrFail();
foreach (MeetingExecutionSession::query()->where('status', 'IN_PROGRESS')->get() as $session) {
    app(EndMeetingExecution::class)->execute($coordinator, Meeting::findOrFail($session->meeting_id));
}
foreach (TerminalMeetingSession::query()->where('status', 'ACTIVE')->get() as $session) {
    app(CloseTerminalMeetingSession::class)->execute($session, 'FLOW-11 canonical invariant cleanup');
}

$secretary = User::query()->where('email', 'executive.secretary@example.test')->firstOrFail();
$authorityUser = User::query()->where('email', 'high.authority@example.test')->firstOrFail();
$directorUsers = [
    'TECH' => User::query()->where('email', 'tech.director@example.test')->firstOrFail(),
    'FIN' => User::query()->where('email', 'finance.director@example.test')->firstOrFail(),
    'HR' => User::query()->where('email', 'hr.director@example.test')->firstOrFail(),
];
$tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
$finance = OrganizationalUnit::query()->where('code', 'FIN')->firstOrFail();
$hr = OrganizationalUnit::query()->where('code', 'HR')->firstOrFail();
$authorityOffice = OrganizationalUnit::query()->where('code', 'AUTH-OFFICE')->firstOrFail();
$authorityEmployee = Employee::query()->where('employee_number', 'EMP-AUTH-001')->firstOrFail();
$organizationAuthority = OrganizationAuthority::query()->firstOrFail();
$canonicalNumber = 'FLOW11-CANONICAL-HA';
$canonical = Meeting::query()->where('meeting_number', $canonicalNumber)->first();
if (!$canonical) {
    $request = MeetingRequest::create(['request_number' => 'FLOW11-CANONICAL-REQUEST', 'flow_type' => 'HIGH_AUTHORITY_INITIATED', 'requesting_unit_id' => null, 'created_by' => $secretary->id, 'subject' => 'جلسهٔ معیار مقام عالی FLOW-11', 'purpose' => 'CANONICAL_INVARIANT', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $secretary->id]);
    $canonical = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => $canonicalNumber, 'title' => $request->subject, 'organizing_unit_id' => $authorityOffice->id, 'coordinator_id' => $secretary->id, 'chairperson_employee_id' => $authorityEmployee->id, 'meeting_date' => now()->addDays(30)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار معیار مقام عالی', 'status' => 'SCHEDULED']);
    MeetingAuthorityContext::create(['meeting_id' => $canonical->id, 'organization_authority_id' => $organizationAuthority->id, 'authority_type' => $organizationAuthority->authority_type, 'authority_position_id' => $organizationAuthority->authority_position_id, 'authority_employee_id' => $authorityEmployee->id, 'authority_office_unit_id' => $authorityOffice->id]);
    foreach ([$tech, $finance, $hr] as $unit) MeetingUnit::create(['meeting_id' => $canonical->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
    foreach ([$tech, $finance, $hr] as $unit) {
        $employee = Employee::query()->where('organizational_unit_id', $unit->id)->whereIn('email', ['tech.director@example.test', 'finance.director@example.test', 'hr.director@example.test'])->firstOrFail();
        MeetingParticipant::create(['meeting_id' => $canonical->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
    }
    $schedule = app(ScheduleVersionService::class)->createNext($canonical, ['meeting_date' => $canonical->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => $canonical->location], $secretary->id);
    app(ScheduleVersionService::class)->applyToMeeting($canonical, $schedule);
    app(ParticipantVersionService::class)->ensureInitialVersion($canonical->fresh(['request.authorityContext', 'participants.employee.unit']), $secretary->id);
    app(StartMeetingExecution::class)->execute($secretary, $canonical->fresh());
    app(EndMeetingExecution::class)->execute($secretary, $canonical->fresh());
    $minutes = app(CreateMeetingMinutesDraft::class)->execute($secretary, $canonical->fresh());
    $minutes = app(UpdateMeetingMinutesDraft::class)->execute($secretary, $minutes, ['summary' => 'خلاصهٔ معیار مقام عالی.', 'general_notes' => 'نسخهٔ canonical.', 'items' => [['agenda_title' => 'موضوع معیار', 'discussion_summary' => 'بحث معیار ثبت شد.']], 'decisions' => [['decision_text' => 'تصمیم معیار ثبت شد.']]]);
    app(SubmitMeetingMinutesVersion::class)->execute($secretary, $minutes);
    foreach (['TECH' => $tech, 'FIN' => $finance, 'HR' => $hr] as $code => $unit) app(AcknowledgeMeetingMinutes::class)->execute($directorUsers[$code], $minutes->fresh(), $unit->id);
    app(ApproveHighAuthorityMeetingMinutes::class)->execute($authorityUser, $minutes->fresh());
    app(FinalizeMeetingMinutes::class)->execute($secretary, $minutes->fresh());
    $canonical = $canonical->fresh();
}
$canonical = $canonical->fresh();
if ($canonical->minutes && $canonical->minutes->status === MeetingMinutes::FINALIZED && !MeetingMinuteDocument::query()->where('meeting_minutes_id', $canonical->minutes->id)->exists()) {
    app(GenerateFinalMeetingMinutesDocument::class)->execute($secretary, $canonical->minutes->fresh());
}

$meetingIds = Meeting::query()->where(function ($query) use ($canonicalNumber): void {
    $query->where('meeting_number', 'like', 'FLOW11-RACE-M-%')->orWhere('meeting_number', $canonicalNumber);
})->pluck('id');
$attendance = MeetingAttendanceRecord::query()->whereIn('meeting_id', $meetingIds)->get();
$minutes = MeetingMinutes::query()->whereIn('meeting_id', $meetingIds)->with(['meeting', 'versions', 'document'])->get();
$privacyColumns = [];
foreach (['meeting_minutes', 'meeting_minute_versions', 'meeting_minute_version_items', 'meeting_minute_version_decisions', 'meeting_minute_attendance_snapshots', 'meeting_minute_reviews', 'meeting_minute_documents'] as $table) {
    foreach (['template_data', 'fingerprint_image', 'raw_sensor_frame', 'probe_template', 'match_score', 'candidate_set_checksum', 'terminal_session_token'] as $column) {
        if (Schema::hasColumn($table, $column)) $privacyColumns[] = $table.'.'.$column;
    }
}
$duplicateRoots = DB::table('meeting_minutes')->select('meeting_id')->groupBy('meeting_id')->havingRaw('COUNT(*) > 1')->count();
$duplicateDocuments = DB::table('meeting_minute_documents')->select('meeting_minutes_id')->groupBy('meeting_minutes_id')->havingRaw('COUNT(*) > 1')->count();
$duplicateExecutionSessions = DB::table('meeting_execution_sessions')->select('meeting_id')->groupBy('meeting_id')->havingRaw('COUNT(*) > 1')->count();
$duplicateRequestMeetings = DB::table('meetings')->select('meeting_request_id')->whereIn('id', $meetingIds)->groupBy('meeting_request_id')->havingRaw('COUNT(*) > 1')->count();
$duplicateFinalizations = DB::table('meeting_actions')->whereIn('meeting_id', $meetingIds)->where('action', 'MINUTES_FINALIZED')->select('meeting_id')->groupBy('meeting_id')->havingRaw('COUNT(*) > 1')->get()->count();

$inventory = Meeting::query()->whereIn('id', $meetingIds)->with(['request.authorityContext', 'authorityContext', 'executionSession', 'minutes.currentVersion.reviews'])->get()->map(function (Meeting $meeting) use ($privacyColumns): array {
    $request = $meeting->request;
    $execution = $meeting->executionSession;
    $root = $meeting->minutes;
    $version = $root?->currentVersion;
    $reviewRows = $version?->reviews ?? collect();
    $decisionCount = $version?->decisions()->count() ?? 0;
    $duplicateDecisionSequences = $version ? DB::table('meeting_minute_version_decisions')->where('meeting_minute_version_id', $version->id)->select('sequence_number')->groupBy('sequence_number')->havingRaw('COUNT(*) > 1')->count() : 0;
    $attendanceAfterEnd = ($execution?->ended_at) ? MeetingAttendanceRecord::query()->where('meeting_id', $meeting->id)->where('recorded_at_server', '>', $execution->ended_at)->count() : 0;
    $executionCount = DB::table('meeting_execution_sessions')->where('meeting_id', $meeting->id)->count();
    $rootCount = DB::table('meeting_minutes')->where('meeting_id', $meeting->id)->count();
    $requestMeetingCount = DB::table('meetings')->where('meeting_request_id', $meeting->meeting_request_id)->count();
    $finalizationCount = DB::table('meeting_actions')->where('meeting_id', $meeting->id)->where('action', 'MINUTES_FINALIZED')->count();
    return [
        'meeting_id' => $meeting->id,
        'meeting_number' => $meeting->meeting_number,
        'meeting_request_id' => $meeting->meeting_request_id,
        'requesting_unit_id' => $request?->requesting_unit_id,
        'request_status' => $request?->status,
        'flow_type' => $request?->flow_type,
        'status' => $meeting->status,
        'execution_session' => $execution ? ['status' => $execution->status, 'schedule_version_id' => $execution->schedule_version_id, 'participant_version_id' => $execution->participant_version_id, 'started_at' => optional($execution->started_at)->toIso8601String(), 'ended_at' => optional($execution->ended_at)->toIso8601String()] : null,
        'minutes' => $root ? ['status' => $root->status, 'current_version_id' => $root->current_version_id, 'version_count' => $root->versions()->count(), 'review_statuses' => $reviewRows->pluck('status')->values()->all()] : null,
        'decision_count' => $decisionCount,
        'duplicate_decision_sequence_count' => $duplicateDecisionSequences,
        'review_requirement_count' => $reviewRows->count(),
        'acknowledgement_count' => $reviewRows->where('status', 'ACKNOWLEDGED')->count(),
        'high_authority_approval_count' => $reviewRows->where('reviewer_type', 'HIGH_AUTHORITY')->whereIn('status', ['ACKNOWLEDGED', 'APPROVED'])->count(),
        'attendance_after_end_count' => $attendanceAfterEnd,
        'duplicate_execution_session_count' => max($executionCount - 1, 0),
        'duplicate_minutes_root_count' => max($rootCount - 1, 0),
        'duplicate_finalization_count' => max($finalizationCount - 1, 0),
        'request_meeting_duplicate_count' => max($requestMeetingCount - 1, 0),
        'biometric_sensitive_fields_in_minutes' => count($privacyColumns),
    ];
})->values()->all();
$canonicalCompleted = collect($inventory)->first(fn (array $row): bool => $row['status'] === 'COMPLETED' && $row['high_authority_approval_count'] > 0);
$attendanceAfterEndTotal = MeetingAttendanceRecord::query()->whereIn('meeting_id', $meetingIds)->whereRaw("recorded_at_server > (SELECT ended_at FROM meeting_execution_sessions WHERE meeting_execution_sessions.meeting_id = meeting_attendance_records.meeting_id LIMIT 1)")->count();
$out = [
    'artifact' => 'FLOW11_CANONICAL_INVARIANTS',
    'database' => DB::connection()->getDatabaseName(),
    'source' => 'flow11_action_race_probe.php plus canonical High Authority fixture plus official domain cleanup',
    'fixture_meetings' => $meetingIds->values()->all(),
    'canonical_completed_fixture' => $canonicalCompleted,
    'meeting_inventory' => $inventory,
    'execution_session_status_counts' => MeetingExecutionSession::query()->whereIn('meeting_id', $meetingIds)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status')->all(),
    'minutes_status_counts' => MeetingMinutes::query()->whereIn('meeting_id', $meetingIds)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status')->all(),
    'official_document_rows' => MeetingMinuteDocument::query()->whereIn('meeting_minutes_id', $minutes->pluck('id'))->count(),
    'attendance_rows' => $attendance->count(),
    'attendance_methods' => $attendance->pluck('attendance_method')->countBy()->all(),
    'attendance_employee_ids' => $attendance->pluck('employee_id')->unique()->values()->all(),
    'attendance_after_end_count' => $attendanceAfterEndTotal,
    'duplicate_execution_session_count' => $duplicateExecutionSessions,
    'duplicate_minutes_root_count' => $duplicateRoots,
    'duplicate_finalization_count' => $duplicateFinalizations,
    'request_meeting_duplicate_count' => $duplicateRequestMeetings,
    'active_execution_sessions' => MeetingExecutionSession::query()->where('status', 'IN_PROGRESS')->count(),
    'active_terminal_sessions' => TerminalMeetingSession::query()->where('status', 'ACTIVE')->count(),
    'active_enrollment_sessions' => Schema::hasTable('biometric_enrollment_sessions') ? DB::table('biometric_enrollment_sessions')->whereIn('status', ['PENDING_CAPTURE', 'IN_PROGRESS'])->count() : 0,
    'biometric_sensitive_fields_in_minutes' => count($privacyColumns),
    'plaintext_probe_columns_present' => $privacyColumns,
    'meeting_status_counts' => Meeting::query()->whereIn('id', $meetingIds)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status')->all(),
];
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
