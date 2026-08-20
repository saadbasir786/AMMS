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
$fixture = json_decode((string) file_get_contents($root.'/flow14_mariadb_large_fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$unitId = (int) json_decode((string) file_get_contents($root.'/flow14_canonical_fixture.json'), true, 512, JSON_THROW_ON_ERROR)['scope_unit_id'];
$user = User::where('email', 'tech.exec@example.test')->firstOrFail();
$makeFilters = static fn (int $perPage): ReportingFilter => ReportingFilter::fromRequest(Request::create('/', 'GET', ['from' => '2026-01-01', 'to' => '2026-12-31', 'involved_unit_id' => $unitId, 'per_page' => $perPage]));
$scope = new ReportingScopeResolver($user);
$scope->requirePermission('reporting.meetings.view');
$captured = [];
DB::listen(static function ($query) use (&$captured): void { $captured[] = ['sql' => $query->sql, 'time_ms' => $query->time, 'bindings' => $query->bindings]; });
$oneRow = app(ReportingQueryService::class)->meetings($scope, $makeFilters(1));
$singleRowQueryCount = count($captured);
$captured = [];
$started = hrtime(true);
$rows = app(ReportingQueryService::class)->meetings($scope, $makeFilters(50));
$elapsed = (hrtime(true) - $started) / 1_000_000;
$selects = array_values(array_filter($captured, fn (array $q): bool => stripos($q['sql'], 'select') === 0));
$explain = [];
foreach ($selects as $candidate) {
    try { $explain[] = ['sql' => $candidate['sql'], 'plan' => DB::select('EXPLAIN '.$candidate['sql'], $candidate['bindings'])]; } catch (Throwable $e) { $explain[] = ['sql' => $candidate['sql'], 'error' => $e->getMessage()]; }
}
$artifact = [
    'gate' => 'FLOW14_MARIADB_NPLUS1_EXPLAIN_GATE', 'pass' => true,
    'environment' => ['driver' => DB::connection()->getDriverName(), 'database' => config('database.connections.mysql.database')],
    'fixture_counts' => $fixture['counts'],
    'rows_returned' => $rows->count(), 'total_rows' => $rows->total(), 'query_count' => count($captured), 'single_row_query_count' => $singleRowQueryCount,
    'execution_time_ms' => round($elapsed, 3), 'n_plus_one_pass' => $rows->count() === 50 && count($captured) <= $singleRowQueryCount * 2,
    'queries' => $captured, 'explain' => $explain,
];
$artifact['pass'] = $artifact['n_plus_one_pass'] && count($explain) > 0 && !isset($explain[0]['error']);
file_put_contents($root.'/flow14_mariadb_nplus1_explain_gate.json', json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($artifact['pass'] ? 0 : 1);
