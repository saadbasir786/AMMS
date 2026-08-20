<?php

declare(strict_types=1);

use App\Models\User;
use App\Reporting\Filters\ReportingFilter;
use App\Reporting\Queries\ReportingQueryService;
use App\Reporting\Scopes\ReportingScopeResolver;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (DB::connection()->getDriverName() !== 'mysql') throw new RuntimeException('MariaDB/MySQL required.');
$root = dirname(__DIR__, 2);
$user = User::where('email', 'tech.exec@example.test')->firstOrFail();
$filters = ReportingFilter::fromRequest(Request::create('/', 'GET', ['from' => '2026-01-01', 'to' => '2026-12-31', 'per_page' => 100]));
$scope = new ReportingScopeResolver($user);
$service = app(ReportingQueryService::class);
$results = [];
$check = static function (string $name, bool $pass, mixed $actual, mixed $expected) use (&$results): void { $results[] = ['name' => $name, 'pass' => $pass, 'actual' => $actual, 'expected' => $expected]; };

DB::transaction(function () use (&$check, $scope, $filters, $service): void {
    $appliedCancellation = (int) DB::table('meeting_exception_cases')->where('case_type', 'CANCELLATION')->where('status', 'APPLIED')->count();
    $dashboard = $service->dashboard($scope, $filters);
    $dashboardCancellation = (int) ($dashboard['summary']['exception_event_counts']['CANCELLATION'] ?? 0);
    $check('A: applied cancellation and executive dashboard share snapshot', $appliedCancellation === $dashboardCancellation, $dashboardCancellation, $appliedCancellation);
});

DB::transaction(function () use (&$check, $scope, $filters, $service): void {
    $source = DB::table('meeting_action_items')->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->pluck('total', 'status')->map(fn ($v) => (int) $v)->all();
    $dashboard = $service->dashboard($scope, $filters);
    $actual = $dashboard['summary']['action_item_status_counts'];
    ksort($source); ksort($actual);
    $check('B: Action Item source summary and dashboard share snapshot', $source === $actual, $actual, $source);
});

$artifact = ['gate' => 'FLOW14_MARIADB_CONSISTENCY_PROBE_AB', 'pass' => !array_filter($results, fn (array $row): bool => !$row['pass']), 'environment' => ['driver' => DB::connection()->getDriverName(), 'database' => config('database.connections.mysql.database')], 'results' => $results];
file_put_contents($root.'/flow14_mariadb_consistency_probe_ab.json', json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($artifact['pass'] ? 0 : 1);
