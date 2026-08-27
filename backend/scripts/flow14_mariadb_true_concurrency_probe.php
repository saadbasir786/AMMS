<?php

declare(strict_types=1);

use App\Actions\ActionItems\VerifyActionItemCompletion;
use App\Actions\Exceptions\ApplyMeetingCancellation;
use App\Actions\Exceptions\ApproveMeetingExceptionReview;
use App\Actions\Exceptions\CreateMeetingExceptionCase;
use App\Actions\Exceptions\SubmitMeetingExceptionCase;
use App\Models\AmmsRoleScope;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingAttendanceInvalidation;
use App\Models\MeetingAttendanceRecord;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingTerminalAssignment;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Reporting\Filters\ReportingFilter;
use App\Reporting\Queries\ReportingQueryService;
use App\Reporting\Scopes\ReportingScopeResolver;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

putenv('DB_CONNECTION=mysql');
require __DIR__.'/../vendor/autoload.php';
/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'mysql']);
if (DB::connection()->getDriverName() !== 'mysql') throw new RuntimeException('MariaDB/MySQL required.');

$root = dirname(__DIR__, 2);
$tag = 'FLOW14-TRUE-RACE-'.Str::upper(Str::random(8));
$raceRoot = storage_path('app/'.$tag);
mkdir($raceRoot, 0775, true);
$owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
$director = User::where('email', 'tech.director@example.test')->firstOrFail();
$executive = User::where('email', 'tech.exec@example.test')->firstOrFail();
$unit = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$employee = Employee::where('email', 'tech.director@example.test')->firstOrFail();
$fixtureUnit = OrganizationalUnit::where('code', 'FLOW14-REPORTING')->firstOrFail();
$coordinatorRole = Role::where('name', 'EXECUTIVE_MANAGEMENT')->where('guard_name', 'sanctum')->firstOrFail();
AmmsRoleScope::firstOrCreate(['user_id' => $owner->id, 'role_id' => $coordinatorRole->id, 'organizational_unit_id' => $fixtureUnit->id]);

$reconnect = static function (): void { DB::purge('mysql'); DB::reconnect('mysql'); };
$connectionId = static fn (): int => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
$waitFor = static function (string $path, float $timeout = 30.0): void { $start = microtime(true); while (!is_file($path)) { if (microtime(true) - $start > $timeout) throw new RuntimeException('Barrier timeout: '.$path); usleep(10000); } };
$signal = static function (string $path): void { file_put_contents($path, json_encode(['at' => microtime(true), 'pid' => getmypid()])); };

$prepareCancellation = static function (string $name) use ($owner, $director, $unit, $employee): array {
    $request = MeetingRequest::create(['request_number' => $name.'-REQ', 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $owner->id, 'subject' => $name.' cancellation race', 'purpose' => 'CONCURRENCY', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
    $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => $name.'-MEETING', 'title' => $name.' cancellation race', 'organizing_unit_id' => $unit->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->addDays(30)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => $name, 'status' => 'SCHEDULED']);
    MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'REQUESTING']);
    MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
    $schedule = app(ScheduleVersionService::class);
    $scheduleVersion = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => $name], $owner->id);
    $schedule->applyToMeeting($meeting, $scheduleVersion);
    $participantVersion = app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);
    $attendance = MeetingAttendanceRecord::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'participant_version_id' => $participantVersion->id, 'attendance_method' => 'MANUAL_OVERRIDE', 'client_event_uuid' => (string) Str::uuid(), 'recorded_at_server' => now(), 'recorded_by_user_id' => $owner->id, 'manual_reason' => 'True concurrency race fixture.']);
    $terminal = Terminal::create(['uuid' => (string) Str::uuid(), 'organization_id' => $unit->organization_id, 'code' => $name.'-TERM', 'name' => $name.' terminal', 'serial_number' => $name.'-SERIAL', 'manufacturer' => 'TEST', 'model' => 'FLOW14', 'platform' => 'TEST', 'os_version' => '1', 'app_version' => '1', 'status' => 'ACTIVE', 'registered_by_user_id' => $owner->id, 'registered_at' => now()]);
    $assignment = MeetingTerminalAssignment::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'terminal_id' => $terminal->id, 'baseline_schedule_version_id' => $meeting->current_schedule_version_id, 'baseline_participant_version_id' => $participantVersion->id, 'valid_from' => now()->subMinute(), 'valid_until' => now()->addHour(), 'status' => 'ASSIGNED', 'assigned_by_user_id' => $owner->id, 'assigned_at' => now()]);
    $session = TerminalMeetingSession::create(['uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'terminal_id' => $terminal->id, 'meeting_id' => $meeting->id, 'schedule_version_id' => $meeting->current_schedule_version_id, 'participant_version_id' => $participantVersion->id, 'status' => 'ACTIVE', 'session_token_hash' => hash('sha256', $name), 'activated_at' => now(), 'expires_at' => now()->addHour()]);
    $terminal->forceFill(['current_session_id' => $session->id])->save();
    $meeting->forceFill(['current_terminal_session_id' => $session->id])->save();
    $case = app(CreateMeetingExceptionCase::class)->execute($meeting->fresh(), $owner, MeetingExceptionCase::CANCELLATION, $name.' cancellation case', false, null, null);
    $case = app(SubmitMeetingExceptionCase::class)->execute($case, $owner);
    $review = $case->fresh(['currentVersion.reviews'])->currentVersion->reviews->firstOrFail();
    app(ApproveMeetingExceptionReview::class)->execute($review, $director);
    return ['meeting_id' => $meeting->id, 'case_id' => $case->id, 'attendance_id' => $attendance->id, 'assignment_id' => $assignment->id, 'session_id' => $session->id];
};

$prepareActionItem = static function (string $name) use ($owner, $unit, $employee): int {
    $followup = DB::table('meeting_decision_followups')->where('decision_number', 'like', 'FLOW14-PERF-DEC-%')->orderBy('id')->first();
    if (!$followup) throw new RuntimeException('Performance follow-up prerequisite missing.');
    $sequence = ((int) DB::table('meeting_action_items')->where('decision_followup_id', $followup->id)->max('sequence_number')) + 1;
    return DB::table('meeting_action_items')->insertGetId(['uuid' => (string) Str::uuid(), 'decision_followup_id' => $followup->id, 'sequence_number' => $sequence, 'title' => $name.' action item', 'description' => 'True concurrency race fixture.', 'responsible_unit_id' => $unit->id, 'assigned_employee_id' => $employee->id, 'status' => MeetingActionItem::PENDING_VERIFICATION, 'progress_percent' => 100, 'due_date' => now()->addDays(4)->toDateString(), 'submitted_for_verification_at' => now(), 'submitted_by_user_id' => $employee->user_id ?? $owner->id, 'created_by_user_id' => $owner->id, 'lock_version' => 1, 'created_at' => now(), 'updated_at' => now()]);
};

$runRace = static function (string $name, string $ordering, callable $dashboardRead, callable $mutation) use ($raceRoot, $reconnect, $waitFor, $signal, $connectionId): array {
    $dir = $raceRoot.'/'.$name.'-'.$ordering; mkdir($dir, 0775, true);
    $worker = static function (string $role, callable $work, ?string $orderedWait = null) use ($dir, $reconnect, $waitFor, $signal, $connectionId): void {
        $reconnect();
        $meta = ['worker' => $role, 'pid' => getmypid(), 'connection_id' => $connectionId(), 'barrier_point' => $dir.'/release', 'operation_start' => microtime(true)];
        file_put_contents($dir.'/'.$role.'.ready.json', json_encode($meta));
        $waitFor($dir.'/release');
        if ($orderedWait !== null) $waitFor($dir.'/'.$orderedWait);
        try { $meta['result'] = $work($meta); $meta['ok'] = true; } catch (Throwable $e) { $meta['ok'] = false; $meta['error'] = ['class' => $e::class, 'message' => $e->getMessage()]; }
        $meta['operation_commit_or_result'] = microtime(true);
        file_put_contents($dir.'/'.$role.'.result.json', json_encode($meta, JSON_UNESCAPED_UNICODE));
        $signal($dir.'/'.$role.'.done');
    };
    $pids = [];
    foreach (['dashboard', 'mutation'] as $role) {
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('fork failed');
        if ($pid === 0) {
            $orderedWait = $ordering === 'DASHBOARD_BEFORE_MUTATION' && $role === 'mutation' ? 'dashboard.done' : ($ordering === 'MUTATION_BEFORE_DASHBOARD' && $role === 'dashboard' ? 'mutation.done' : null);
            $worker($role, $role === 'dashboard' ? $dashboardRead : $mutation, $orderedWait);
            exit(0);
        }
        $pids[] = $pid;
    }
    foreach (['dashboard', 'mutation'] as $role) $waitFor($dir.'/'.$role.'.ready.json');
    $signal($dir.'/release');
    foreach ($pids as $pid) pcntl_waitpid($pid, $status);
    $results = [];
    foreach (['dashboard', 'mutation'] as $role) $results[$role] = json_decode((string) file_get_contents($dir.'/'.$role.'.result.json'), true, 512, JSON_THROW_ON_ERROR);
    return ['name' => $name, 'ordering' => $ordering, 'barrier' => ['release' => $dir.'/release', 'dashboard_ready' => $dir.'/dashboard.ready.json', 'mutation_ready' => $dir.'/mutation.ready.json'], 'workers' => $results];
};

$dashboardRead = static function (int $meetingId, int $execUserId) use ($connectionId): array {
    $before = Meeting::findOrFail($meetingId);
    $beforeInvalidation = MeetingAttendanceInvalidation::where('meeting_attendance_record_id', DB::table('meeting_attendance_records')->where('meeting_id', $meetingId)->value('id'))->count();
    $scope = new ReportingScopeResolver(User::findOrFail($execUserId));
    $filters = ReportingFilter::fromRequest(Request::create('/', 'GET', ['from' => '2026-01-01', 'to' => '2026-12-31', 'per_page' => 50]));
    $dashboard = app(ReportingQueryService::class)->dashboard($scope, $filters);
    $after = Meeting::findOrFail($meetingId);
    $afterInvalidation = MeetingAttendanceInvalidation::where('meeting_attendance_record_id', DB::table('meeting_attendance_records')->where('meeting_id', $meetingId)->value('id'))->count();
    return ['connection_id' => $connectionId(), 'target_status_before_read' => $before->status, 'target_status_after_read' => $after->status, 'target_invalidation_before_read' => $beforeInvalidation, 'target_invalidation_after_read' => $afterInvalidation, 'dashboard_snapshot' => ['meeting_status_counts' => $dashboard['summary']['meeting_status_counts'], 'exception_event_counts' => $dashboard['summary']['exception_event_counts'], 'official_attendance_count' => $dashboard['summary']['official_attendance_count'], 'invalidated_attendance_count' => $dashboard['summary']['invalidated_attendance_count']], 'snapshot_finished_at' => microtime(true)];
};
$mutationCancel = static function (int $caseId, int $meetingId, int $attendanceId, int $ownerId) use ($connectionId): array {
    $result = app(ApplyMeetingCancellation::class)->execute(MeetingExceptionCase::with('meeting')->findOrFail($caseId), User::findOrFail($ownerId));
    return ['connection_id' => $connectionId(), 'action' => 'ApplyMeetingCancellation', 'returned_status' => $result->status, 'meeting_status_after_commit' => Meeting::findOrFail($meetingId)->status, 'attendance_invalidation_after_commit' => MeetingAttendanceInvalidation::where('meeting_attendance_record_id', $attendanceId)->count(), 'commit_finished_at' => microtime(true)];
};

$cancelBefore = $prepareCancellation($tag.'-CANCEL-BEFORE');
$cancelAfter = $prepareCancellation($tag.'-CANCEL-AFTER');
$cancelRaceBefore = $runRace('cancellation-dashboard', 'DASHBOARD_BEFORE_MUTATION', fn () => $dashboardRead($cancelBefore['meeting_id'], $executive->id), fn () => $mutationCancel($cancelBefore['case_id'], $cancelBefore['meeting_id'], $cancelBefore['attendance_id'], $owner->id));
$cancelRaceAfter = $runRace('cancellation-dashboard', 'MUTATION_BEFORE_DASHBOARD', fn () => $dashboardRead($cancelAfter['meeting_id'], $executive->id), fn () => $mutationCancel($cancelAfter['case_id'], $cancelAfter['meeting_id'], $cancelAfter['attendance_id'], $owner->id));

$dashboardActions = static function (int $execUserId) use ($connectionId): array {
    $scope = new ReportingScopeResolver(User::findOrFail($execUserId));
    $filters = ReportingFilter::fromRequest(Request::create('/', 'GET', ['from' => '2026-01-01', 'to' => '2026-12-31', 'per_page' => 50]));
    $dashboard = app(ReportingQueryService::class)->dashboard($scope, $filters);
    return ['connection_id' => $connectionId(), 'action_item_status_counts' => $dashboard['summary']['action_item_status_counts'], 'snapshot_finished_at' => microtime(true)];
};
$completeAction = static function (int $itemId, int $ownerId) use ($connectionId): array {
    $result = app(VerifyActionItemCompletion::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($itemId), User::findOrFail($ownerId));
    return ['connection_id' => $connectionId(), 'action' => 'VerifyActionItemCompletion', 'returned_status' => $result->status, 'item_status_after_commit' => MeetingActionItem::findOrFail($itemId)->status, 'commit_finished_at' => microtime(true)];
};
$actionItemBefore = $prepareActionItem($tag.'-ACTION-BEFORE');
$actionItemAfter = $prepareActionItem($tag.'-ACTION-AFTER');
$actionRaceBefore = $runRace('action-dashboard', 'DASHBOARD_BEFORE_MUTATION', fn () => $dashboardActions($executive->id), fn () => $completeAction($actionItemBefore, $owner->id));
$actionRaceAfter = $runRace('action-dashboard', 'MUTATION_BEFORE_DASHBOARD', fn () => $dashboardActions($executive->id), fn () => $completeAction($actionItemAfter, $owner->id));

$load = static fn (string $path): array => json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$orderedCheck = static function (array $race, string $domain): bool {
    $dashboard = $race['workers']['dashboard']['result'] ?? [];
    $mutation = $race['workers']['mutation']['result'] ?? [];
    if ($domain === 'cancellation') {
        return ($race['ordering'] === 'DASHBOARD_BEFORE_MUTATION' && $dashboard['target_status_before_read'] === 'SCHEDULED' && $dashboard['target_invalidation_before_read'] === 0 && $mutation['meeting_status_after_commit'] === 'CANCELLED' && $mutation['attendance_invalidation_after_commit'] === 1)
            || ($race['ordering'] === 'MUTATION_BEFORE_DASHBOARD' && $mutation['meeting_status_after_commit'] === 'CANCELLED' && $mutation['attendance_invalidation_after_commit'] === 1 && $dashboard['target_status_before_read'] === 'CANCELLED' && $dashboard['target_invalidation_before_read'] === 1);
    }
    $before = $race['ordering'] === 'DASHBOARD_BEFORE_MUTATION';
    $counts = $dashboard['action_item_status_counts'] ?? [];
    $mutationStatus = $mutation['item_status_after_commit'] ?? null;
    return $mutationStatus === 'COMPLETED' && (($before && ($counts['PENDING_VERIFICATION'] ?? 0) >= 2 && ($counts['COMPLETED'] ?? 0) < 65) || (!$before && ($counts['PENDING_VERIFICATION'] ?? 0) >= 1 && ($counts['COMPLETED'] ?? 0) >= 65));
};
$finalCancellation = static fn (array $f): array => ['meeting_status' => Meeting::findOrFail($f['meeting_id'])->status, 'raw_attendance_count' => MeetingAttendanceRecord::where('meeting_id', $f['meeting_id'])->count(), 'invalidated_attendance_count' => MeetingAttendanceInvalidation::whereIn('meeting_attendance_record_id', MeetingAttendanceRecord::where('meeting_id', $f['meeting_id'])->pluck('id'))->count(), 'official_attendance_count' => MeetingAttendanceRecord::where('meeting_id', $f['meeting_id'])->whereDoesntHave('invalidation')->count()];
$finalAction = static fn (int $id): array => ['item_status' => MeetingActionItem::findOrFail($id)->status];
$artifact = [
    'gate' => 'FLOW14_MARIADB_TRUE_CONCURRENCY_PROBE',
    'pass' => true,
    'environment' => ['driver' => DB::connection()->getDriverName(), 'database' => config('database.connections.mysql.database'), 'tag' => $tag],
    'probes' => [
        'A_cancellation_dashboard' => [
            'orderings' => [$cancelRaceBefore, $cancelRaceAfter],
            'final_database_state' => ['before_ordering' => $finalCancellation($cancelBefore), 'after_ordering' => $finalCancellation($cancelAfter)],
            'ordering_pass' => $orderedCheck($cancelRaceBefore, 'cancellation') && $orderedCheck($cancelRaceAfter, 'cancellation'),
        ],
        'B_action_completion_dashboard' => [
            'before_status_counts' => ['item_before' => 'PENDING_VERIFICATION', 'item_after' => 'PENDING_VERIFICATION'],
            'orderings' => [$actionRaceBefore, $actionRaceAfter],
            'final_database_state' => ['before_ordering' => $finalAction($actionItemBefore), 'after_ordering' => $finalAction($actionItemAfter)],
            'ordering_pass' => $orderedCheck($actionRaceBefore, 'action') && $orderedCheck($actionRaceAfter, 'action'),
        ],
    ],
    'barrier_protocol' => 'Each ordering uses two forked workers, each with DB::purge/reconnect, explicit ready files, a release barrier, and a role-specific completion barrier; no worker runs on the parent connection.',
];
$artifact['pass'] = $artifact['probes']['A_cancellation_dashboard']['ordering_pass'] && $artifact['probes']['B_action_completion_dashboard']['ordering_pass'];
file_put_contents($root.'/flow14_mariadb_true_concurrency_probe.json', json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($artifact['pass'] ? 0 : 1);
