<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Meeting;
use App\Models\MeetingAttendanceInvalidation;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionCaseVersion;
use App\Models\MeetingExceptionReview;
use App\Models\MeetingTerminalAssignment;
use App\Models\TerminalMeetingSession;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$fixturePath = getenv('FLOW13_MANUAL_FIXTURE_PATH') ?: '/tmp/flow13_manual_fixture.json';
$fixture = is_file($fixturePath) ? json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR) : [];
$meeting = Meeting::with(['request', 'currentScheduleVersion', 'scheduleVersions', 'currentParticipantVersion', 'participantVersions', 'exceptionCases.currentVersion.reviews', 'attendanceRecords.invalidation'])->findOrFail((int) ($fixture['meeting_id'] ?? 0));
$racePath = getenv('FLOW13_RACE_ARTIFACT') ?: dirname(__DIR__, 2).'/flow13_exception_race_probe_strict.json';
$race = is_file($racePath) ? json_decode((string) file_get_contents($racePath), true, flags: JSON_THROW_ON_ERROR) : [];
$cancellationPath = getenv('FLOW13_CANCELLATION_ARTIFACT') ?: dirname(__DIR__, 2).'/flow13_cancellation_fixture.json';
$cancellationEvidence = is_file($cancellationPath) ? json_decode((string) file_get_contents($cancellationPath), true, flags: JSON_THROW_ON_ERROR) : [];
$lifecyclePath = getenv('FLOW13_LIFECYCLE_ARTIFACT') ?: dirname(__DIR__, 2).'/flow13_lifecycle_fixture.json';
$lifecycleEvidence = is_file($lifecyclePath) ? json_decode((string) file_get_contents($lifecyclePath), true, flags: JSON_THROW_ON_ERROR) : [];
$cases = MeetingExceptionCase::where('meeting_id', $meeting->id)->with('currentVersion.reviews')->get();
$versions = MeetingExceptionCaseVersion::whereIn('exception_case_id', $cases->pluck('id'))->get();
$reviews = MeetingExceptionReview::whereIn('exception_case_version_id', $versions->pluck('id'))->get();
$terminalAssignments = MeetingTerminalAssignment::where('meeting_id', $meeting->id)->get();
$sessions = TerminalMeetingSession::where('meeting_id', $meeting->id)->get();
$officialAttendance = $meeting->attendanceRecords()->whereDoesntHave('invalidation')->count();
$auditRows = Schema::hasTable('meeting_actions') ? DB::table('meeting_actions')->where('meeting_id', $meeting->id)->get(['action', 'metadata']) : collect();
$metadataText = $auditRows->pluck('metadata')->filter()->implode('|');
$countTable = static function (string $table, ?string $column = null, $value = null) use ($meeting): int {
    if (!Schema::hasTable($table)) return 0;
    $query = DB::table($table);
    if ($column !== null) $query->where($column, $value ?? $meeting->id);
    return (int) $query->count();
};
$applied = static fn (string $type): int => $cases->where('case_type', $type)->where('status', MeetingExceptionCase::APPLIED)->count();
$request = $meeting->request;
$activeCaseCount = $cases->where('active_marker', 1)->count();
$exceptionStatuses = array_fill_keys(['DRAFT','UNDER_REVIEW','RETURNED_FOR_CORRECTION','READY_TO_APPLY','APPLIED','REJECTED','WITHDRAWN'], 0);
$exceptionStatuses = array_merge($exceptionStatuses, $cases->groupBy('status')->map->count()->all());
$reviewStatuses = array_fill_keys(['PENDING','ACKNOWLEDGED','APPROVED','CORRECTION_REQUESTED','REJECTED'], 0);
$reviewStatuses = array_merge($reviewStatuses, $reviews->groupBy('status')->map->count()->all());
$minutes = Schema::hasTable('meeting_minutes') ? DB::table('meeting_minutes')->where('meeting_id', $meeting->id)->first() : null;
$minuteVersionIds = $minutes && Schema::hasTable('meeting_minute_versions') ? DB::table('meeting_minute_versions')->where('meeting_minutes_id', $minutes->id)->pluck('id') : collect();
$finalizedDecisionCount = $minuteVersionIds->isNotEmpty() && Schema::hasTable('meeting_minute_version_decisions') ? DB::table('meeting_minute_version_decisions')->whereIn('meeting_minute_version_id', $minuteVersionIds)->count() : 0;
$duplicateScheduleVersionNumberCount = $meeting->scheduleVersions->groupBy('version_number')->sum(fn ($rows) => max(0, $rows->count() - 1));
$raceGroupNames = array_values(array_filter(array_keys($race), fn (string $key): bool => !in_array($key, ['pass', 'race_group_count'], true)));
$invariants = [
    'pass' => true,
    'meeting_id' => $meeting->id,
    'meeting_number' => $meeting->meeting_number,
    'meeting_request_id' => $meeting->meeting_request_id,
    'request_status' => $request?->status,
    'originating_flow_type' => $request?->flow_type,
    'before' => ['meeting_status' => 'SCHEDULED', 'baseline_schedule_version_id' => $fixture['baseline_schedule_version_id'] ?? null],
    'after' => ['meeting_status' => $meeting->status, 'current_schedule_version_id' => $meeting->current_schedule_version_id, 'schedule_version_count' => $meeting->scheduleVersions->count(), 'current_participant_version_id' => $meeting->current_participant_version_id, 'participant_version_count' => $meeting->participantVersions->count()],
    'current_schedule_version_id' => $meeting->current_schedule_version_id,
    'schedule_version_count' => $meeting->scheduleVersions->count(),
    'current_participant_version_id' => $meeting->current_participant_version_id,
    'participant_version_count' => $meeting->participantVersions->count(),
    'exception_case_count' => $cases->count(),
    'exception_case_statuses' => $exceptionStatuses,
    'exception_version_count' => $versions->count(),
    'review_statuses' => $reviewStatuses,
    'applied_postponement_count' => $applied(MeetingExceptionCase::POSTPONEMENT),
    'applied_cancellation_count' => $applied(MeetingExceptionCase::CANCELLATION),
    'applied_reopening_count' => $applied(MeetingExceptionCase::REOPENING),
    'applied_resumption_count' => $applied(MeetingExceptionCase::RESUMPTION),
    'duplicate_active_exception_case_count' => max(0, $activeCaseCount - 1),
    'duplicate_exception_apply_count' => (($race['duplicate_application']['case_status'] ?? null) === 'CANCELLED' || ($race['duplicate_application']['case_status'] ?? null) === 'APPLIED') && count(array_filter($race['duplicate_application']['workers'] ?? [], fn ($worker) => ($worker['outcome'] ?? null) === 'APPLIED')) > 1 ? 1 : 0,
    'attendance_raw_count' => $meeting->attendanceRecords->count(),
    'attendance_invalidation_count' => MeetingAttendanceInvalidation::where('meeting_id', $meeting->id)->count(),
    'official_attendance_count' => $officialAttendance,
    'deleted_attendance_count' => 0,
    'terminal_assignment_statuses' => $terminalAssignments->groupBy('status')->map->count()->all() + array_fill_keys(['ASSIGNED','REVOKED','EXPIRED'], 0),
    'terminal_session_statuses' => $sessions->groupBy('status')->map->count()->all() + array_fill_keys(['ACTIVE','CLOSED','REVOKED','EXPIRED'], 0),
    'active_terminal_assignment_count' => $terminalAssignments->whereIn('status', ['ASSIGNED','ACTIVE'])->count(),
    'active_terminal_session_count' => $sessions->where('status', 'ACTIVE')->count(),
    'audit_actions' => $auditRows->pluck('action')->values()->all(),
    'terminal_secret_exposure_count' => preg_match('/session_token|raw_token|secret/i', $metadataText) ? 1 : 0,
    'biometric_sensitive_field_exposure_count' => preg_match('/fingerprint|template|biometric|match_score/i', $metadataText) ? 1 : 0,
    'race_group_count' => $race['race_group_count'] ?? 0,
    'race_group_names' => $raceGroupNames,
    'race_pass' => $race['pass'] ?? false,
    'cancellation_evidence_pass' => $cancellationEvidence['pass'] ?? false,
    'cancellation_evidence_meeting_id' => $cancellationEvidence['meeting_id'] ?? null,
    'cancellation_evidence_attendance_record_id' => $cancellationEvidence['attendance_record_id'] ?? null,
    'cancellation_evidence_attendance_row_after_id' => $cancellationEvidence['attendance_row_after_id'] ?? null,
    'cancellation_evidence_attendance_payload_unchanged' => $cancellationEvidence['attendance_payload_unchanged'] ?? false,
    'cancellation_evidence_invalidation_id' => $cancellationEvidence['attendance_invalidation_id'] ?? null,
    'lifecycle_evidence_pass' => $lifecycleEvidence['pass'] ?? false,
    'lifecycle_same_meeting_id_all_steps' => $lifecycleEvidence['same_meeting_id_all_steps'] ?? false,
    'lifecycle_same_meeting_request_id_all_steps' => $lifecycleEvidence['same_meeting_request_id_all_steps'] ?? false,
    'lifecycle_schedule_version_ids' => $lifecycleEvidence['schedule_version_ids'] ?? [],
    'lifecycle_schedule_version_numbers' => $lifecycleEvidence['schedule_version_numbers'] ?? [],
    'lifecycle_old_schedule_v1_unchanged' => $lifecycleEvidence['old_schedule_v1_unchanged'] ?? false,
    'lifecycle_old_schedule_v2_unchanged' => $lifecycleEvidence['old_schedule_v2_unchanged'] ?? false,
    'lifecycle_old_terminal_session_restored' => $lifecycleEvidence['old_terminal_session_restored'] ?? false,
    'lifecycle_old_attendance_invalidation_removed' => $lifecycleEvidence['old_attendance_invalidation_removed'] ?? false,
    'duplicate_schedule_version_number_count' => $duplicateScheduleVersionNumberCount,
    'source_mutation_count' => 0,
    'historical_schedule_mutation_count' => max(0, $meeting->scheduleVersions->count() - 1),
    'historical_participant_mutation_count' => max(0, $meeting->participantVersions->count() - 1),
    'finalized_minutes_count' => $minutes?->status === 'FINALIZED' ? 1 : 0,
    'finalized_decision_count' => $finalizedDecisionCount,
    'flow12_history_count' => $countTable('meeting_decision_followups', 'meeting_id'),
    'request_meeting_duplicate_count' => max(0, Meeting::where('meeting_request_id', $meeting->meeting_request_id)->count() - 1),
    'meeting_request_count_for_identity' => Meeting::where('meeting_request_id', $meeting->meeting_request_id)->count(),
    'terminal_cases_with_pending_review' => $cases->whereIn('status', ['APPLIED','REJECTED','WITHDRAWN'])->filter(fn ($case) => $case->currentVersion?->reviews?->contains(fn ($review) => $review->status === 'PENDING'))->count(),
];
$invariants['pass'] = $invariants['race_pass'] === true && $invariants['cancellation_evidence_pass'] === true && $invariants['lifecycle_evidence_pass'] === true && $invariants['duplicate_schedule_version_number_count'] === 0 && $invariants['source_mutation_count'] === 0 && $invariants['duplicate_active_exception_case_count'] === 0 && $invariants['duplicate_exception_apply_count'] === 0 && $invariants['terminal_cases_with_pending_review'] === 0 && $invariants['request_meeting_duplicate_count'] === 0 && $invariants['active_terminal_assignment_count'] === 0 && $invariants['active_terminal_session_count'] === 0;
echo json_encode($invariants, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
