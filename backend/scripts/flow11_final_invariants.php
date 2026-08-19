<?php

declare(strict_types=1);

use App\Actions\Meetings\EndMeetingExecution;
use App\Actions\Terminals\CloseTerminalMeetingSession;
use App\Models\Meeting;
use App\Models\MeetingAttendanceRecord;
use App\Models\MeetingExecutionSession;
use App\Models\MeetingMinuteDocument;
use App\Models\MeetingMinutes;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$actor = User::query()->where('email', 'tech.coordinator@example.test')->firstOrFail();
foreach (MeetingExecutionSession::query()->where('status', 'IN_PROGRESS')->get() as $session) {
    app(EndMeetingExecution::class)->execute($actor, Meeting::findOrFail($session->meeting_id));
}
foreach (TerminalMeetingSession::query()->where('status', 'ACTIVE')->get() as $session) {
    app(CloseTerminalMeetingSession::class)->execute($session, 'FLOW-11 canonical invariant cleanup');
}

$meetingIds = Meeting::query()->where('meeting_number', 'like', 'FLOW11-RACE-M-%')->pluck('id');
$attendance = MeetingAttendanceRecord::query()->whereIn('meeting_id', $meetingIds)->get();
$minutes = MeetingMinutes::query()->whereIn('meeting_id', $meetingIds)->with(['meeting', 'versions', 'document'])->get();
$privacyColumns = [];
foreach (['meeting_minutes', 'meeting_minute_versions', 'meeting_minute_version_items', 'meeting_minute_version_decisions', 'meeting_minute_attendance_snapshots', 'meeting_minute_reviews', 'meeting_minute_documents'] as $table) {
    foreach (['template_data', 'fingerprint_image', 'raw_sensor_frame', 'probe_template', 'match_score', 'candidate_set_checksum'] as $column) {
        if (Schema::hasColumn($table, $column)) $privacyColumns[] = $table.'.'.$column;
    }
}
$duplicateRoots = DB::table('meeting_minutes')->select('meeting_id')->groupBy('meeting_id')->havingRaw('COUNT(*) > 1')->count();
$duplicateDocuments = DB::table('meeting_minute_documents')->select('meeting_minutes_id')->groupBy('meeting_minutes_id')->havingRaw('COUNT(*) > 1')->count();
$out = [
    'artifact' => 'FLOW11_CANONICAL_INVARIANTS',
    'database' => DB::connection()->getDatabaseName(),
    'source' => 'flow11_action_race_probe.php plus official domain cleanup',
    'fixture_meetings' => $meetingIds->values()->all(),
    'meeting_inventory' => Meeting::query()->whereIn('id', $meetingIds)->with(['executionSession', 'minutes.currentVersion.reviews'])->get()->map(fn ($m): array => [
        'meeting_id' => $m->id,
        'meeting_number' => $m->meeting_number,
        'status' => $m->status,
        'execution_session' => $m->executionSession ? ['status' => $m->executionSession->status, 'schedule_version_id' => $m->executionSession->schedule_version_id, 'participant_version_id' => $m->executionSession->participant_version_id] : null,
        'minutes' => $m->minutes ? ['status' => $m->minutes->status, 'current_version_id' => $m->minutes->current_version_id, 'version_count' => $m->minutes->versions()->count(), 'review_statuses' => $m->minutes->currentVersion?->reviews?->pluck('status')->values()->all() ?? []] : null,
    ])->values()->all(),
    'execution_session_status_counts' => MeetingExecutionSession::query()->whereIn('meeting_id', $meetingIds)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status')->all(),
    'minutes_status_counts' => MeetingMinutes::query()->whereIn('meeting_id', $meetingIds)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status')->all(),
    'official_document_rows' => MeetingMinuteDocument::query()->whereIn('meeting_minutes_id', $minutes->pluck('id'))->count(),
    'attendance_rows' => $attendance->count(),
    'attendance_methods' => $attendance->pluck('attendance_method')->countBy()->all(),
    'attendance_employee_ids' => $attendance->pluck('employee_id')->unique()->values()->all(),
    'duplicate_minutes_roots' => $duplicateRoots,
    'duplicate_official_documents' => $duplicateDocuments,
    'active_execution_sessions' => MeetingExecutionSession::query()->where('status', 'IN_PROGRESS')->count(),
    'active_terminal_sessions' => TerminalMeetingSession::query()->where('status', 'ACTIVE')->count(),
    'active_enrollment_sessions' => Schema::hasTable('biometric_enrollment_sessions') ? DB::table('biometric_enrollment_sessions')->whereIn('status', ['PENDING_CAPTURE', 'IN_PROGRESS'])->count() : 0,
    'plaintext_probe_columns_present' => $privacyColumns,
    'meeting_status_counts' => Meeting::query()->whereIn('id', $meetingIds)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status')->all(),
];
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
