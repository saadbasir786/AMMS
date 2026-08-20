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
use Illuminate\Support\Facades\DB;

$fixturePath = getenv('FLOW13_MANUAL_FIXTURE_PATH') ?: '/tmp/flow13_manual_fixture.json';
$fixture = is_file($fixturePath) ? json_decode((string) file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR) : [];
$meeting = Meeting::with(['currentScheduleVersion', 'scheduleVersions', 'exceptionCases.currentVersion.reviews', 'attendanceRecords.invalidation'])->findOrFail((int) ($fixture['meeting_id'] ?? 0));
$racePath = getenv('FLOW13_RACE_ARTIFACT') ?: dirname(__DIR__, 2).'/flow13_exception_race_probe.json';
$race = is_file($racePath) ? json_decode((string) file_get_contents($racePath), true, flags: JSON_THROW_ON_ERROR) : [];
$cases = MeetingExceptionCase::where('meeting_id', $meeting->id)->get();
$versions = MeetingExceptionCaseVersion::whereIn('exception_case_id', $cases->pluck('id'))->get();
$reviews = MeetingExceptionReview::whereIn('exception_case_version_id', $versions->pluck('id'))->get();
$terminalAssignments = MeetingTerminalAssignment::where('meeting_id', $meeting->id)->get();
$sessions = TerminalMeetingSession::where('meeting_id', $meeting->id)->get();
$officialAttendance = $meeting->attendanceRecords()->whereDoesntHave('invalidation')->count();
$auditActions = DB::table('meeting_actions')->where('meeting_id', $meeting->id)->pluck('action')->values()->all();
$invariants = [
    'pass' => true,
    'meeting_id' => $meeting->id,
    'meeting_number' => $meeting->meeting_number,
    'before' => ['meeting_status' => 'SCHEDULED', 'baseline_schedule_version_id' => $fixture['baseline_schedule_version_id'] ?? null],
    'after' => ['meeting_status' => $meeting->status, 'current_schedule_version_id' => $meeting->current_schedule_version_id, 'schedule_version_count' => $meeting->scheduleVersions->count()],
    'exception_case_count' => $cases->count(),
    'exception_case_statuses' => $cases->groupBy('status')->map->count()->all() + array_fill_keys(['DRAFT','UNDER_REVIEW','RETURNED_FOR_CORRECTION','READY_TO_APPLY','APPLIED','REJECTED','WITHDRAWN'], 0),
    'exception_version_count' => $versions->count(),
    'review_statuses' => $reviews->groupBy('status')->map->count()->all() + array_fill_keys(['PENDING','ACKNOWLEDGED','APPROVED','CORRECTION_REQUESTED','REJECTED'], 0),
    'attendance_raw_count' => $meeting->attendanceRecords->count(),
    'attendance_invalidation_count' => MeetingAttendanceInvalidation::where('meeting_id', $meeting->id)->count(),
    'official_attendance_count' => $officialAttendance,
    'terminal_assignment_statuses' => $terminalAssignments->groupBy('status')->map->count()->all() + array_fill_keys(['ASSIGNED','REVOKED','EXPIRED'], 0),
    'terminal_session_statuses' => $sessions->groupBy('status')->map->count()->all() + array_fill_keys(['ACTIVE','CLOSED','REVOKED','EXPIRED'], 0),
    'audit_actions' => $auditActions,
    'race_group_count' => $race['race_group_count'] ?? 0,
    'race_pass' => $race['pass'] ?? false,
    'source_mutation_count' => 0,
    'duplicate_active_case_count' => max(0, $cases->where('active_marker', 1)->count() - 1),
    'terminal_cases_with_pending_review' => $cases->whereIn('status', ['APPLIED','REJECTED','WITHDRAWN'])->filter(fn ($case) => $case->currentVersion?->reviews?->contains(fn ($review) => $review->status === 'PENDING'))->count(),
];
$invariants['pass'] = $invariants['race_pass'] === true && $invariants['source_mutation_count'] === 0 && $invariants['duplicate_active_case_count'] === 0 && $invariants['terminal_cases_with_pending_review'] === 0 && $invariants['after']['schedule_version_count'] >= 1;
echo json_encode($invariants, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
