<?php

declare(strict_types=1);

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingAttendanceRecord;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\MeetingRequest;
use App\Models\User;
use App\Reporting\Filters\ReportingFilter;
use App\Reporting\Queries\ReportingQueryService;
use App\Reporting\Scopes\ReportingScopeResolver;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = User::where('email', 'high.authority@example.test')->firstOrFail();
$request = Illuminate\Http\Request::create('/', 'GET', ['from' => '2026-01-01', 'to' => '2026-12-31', 'per_page' => 50]);
$filters = ReportingFilter::fromRequest($request);
$scope = new ReportingScopeResolver($user);
$scope->requirePermission('reporting.meetings.view');
$queries = app(ReportingQueryService::class);
$captured = [];
DB::listen(static function ($query) use (&$captured): void {
    $captured[] = ['sql' => $query->sql, 'time_ms' => $query->time, 'bindings' => $query->bindings];
});
$started = hrtime(true);
$dashboard = $queries->dashboard($scope, $filters);
$dashboardTime = (hrtime(true) - $started) / 1_000_000;
$beforeRegister = count($captured);
$started = hrtime(true);
$register = $queries->meetings($scope, $filters);
$registerTime = (hrtime(true) - $started) / 1_000_000;
$registerQueries = count($captured) - $beforeRegister;
$slowest = collect($captured)->sortByDesc('time_ms')->first();
$explain = [];
if ($slowest && preg_match('/select/i', $slowest['sql'])) {
    try {
        $explain = DB::select('EXPLAIN '.$slowest['sql'], $slowest['bindings']);
    } catch (Throwable $e) {
        $explain = ['error' => $e->getMessage()];
    }
}
$artifact = [
    'pass' => true,
    'environment' => ['connection' => config('database.default'), 'driver' => DB::connection()->getDriverName(), 'timezone' => config('app.timezone')],
    'fixture_size' => [
        'meeting_count' => Meeting::count(),
        'request_count' => MeetingRequest::count(),
        'participant_count' => DB::table('meeting_participant_version_members')->count(),
        'attendance_count' => MeetingAttendanceRecord::count(),
        'decision_count' => MeetingMinuteVersionDecision::count(),
        'followup_count' => MeetingDecisionFollowUp::count(),
        'action_item_count' => MeetingActionItem::count(),
        'exception_count' => MeetingExceptionCase::count(),
    ],
    'endpoints' => [
        'executive_dashboard' => ['query_count' => $beforeRegister, 'execution_time_ms' => round($dashboardTime, 3)],
        'meeting_register_50_rows' => ['query_count' => $registerQueries, 'execution_time_ms' => round($registerTime, 3), 'rows_returned' => $register->count()],
    ],
    'query_count' => count($captured),
    'largest_query_time_ms' => $slowest ? $slowest['time_ms'] : 0,
    'slowest_query' => $slowest ? ['sql' => $slowest['sql'], 'time_ms' => $slowest['time_ms']] : null,
    'explain_summary' => $explain,
    'n_plus_one_gate' => $registerQueries <= 12,
];
$artifact['pass'] = $artifact['n_plus_one_gate'];
echo json_encode($artifact, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
