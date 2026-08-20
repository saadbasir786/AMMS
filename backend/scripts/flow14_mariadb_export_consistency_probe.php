<?php

declare(strict_types=1);

use App\Models\ReportExport;
use App\Models\ReportExportAudit;
use App\Models\User;
use App\Policies\ReportExportPolicy;
use App\Reporting\Exports\ReportExportManager;
use App\Reporting\Metrics\ReportingMetricCatalog;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (DB::connection()->getDriverName() !== 'mysql') throw new RuntimeException('MariaDB/MySQL required.');
$root = dirname(__DIR__, 2);
$owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
$organizationId = (int) DB::table('employees')->join('organizational_units', 'organizational_units.id', '=', 'employees.organizational_unit_id')->where('employees.id', $owner->employee_id)->value('organizational_units.organization_id');
$payload = ['from' => '2026-01-01', 'to' => '2026-12-31', 'timezone' => 'Asia/Kabul', 'per_page' => 50];
$makeExport = static fn (): ReportExport => ReportExport::create([
    'uuid' => (string) Str::uuid(), 'organization_id' => $organizationId, 'requested_by_user_id' => $owner->id,
    'report_type' => 'MEETING_REGISTER', 'format' => 'XLSX', 'filter_payload' => $payload,
    'scope_descriptor' => ['mode' => 'ORGANIZATION', 'organization_id' => $organizationId], 'status' => ReportExport::PENDING,
    'definition_version' => ReportingMetricCatalog::DEFINITION_VERSION, 'requested_at' => now(),
]);
$results = [];
$check = static function (string $name, bool $pass, mixed $actual = null, mixed $expected = null) use (&$results): void { $results[] = ['name' => $name, 'pass' => $pass, 'actual' => $actual, 'expected' => $expected]; };

$race = $makeExport();
$workerDir = storage_path('app/flow14-race-'.Str::uuid());
mkdir($workerDir, 0775, true);
$pids = [];
for ($worker = 1; $worker <= 2; $worker++) {
    $pid = pcntl_fork();
    if ($pid === -1) throw new RuntimeException('pcntl_fork failed');
    if ($pid === 0) {
        try {
            DB::purge('mysql');
            $generated = app(ReportExportManager::class)->generate($race->id);
            file_put_contents($workerDir.'/'.$worker.'.json', json_encode(['status' => $generated->status, 'checksum' => $generated->checksum, 'path' => $generated->storage_path]));
            exit(0);
        } catch (Throwable $e) {
            file_put_contents($workerDir.'/'.$worker.'.json', json_encode(['error' => $e->getMessage()]));
            exit(1);
        }
    }
    $pids[] = $pid;
}
foreach ($pids as $pid) pcntl_waitpid($pid, $status);
$workerResults = [];
foreach ([1, 2] as $worker) $workerResults[] = json_decode((string) file_get_contents($workerDir.'/'.$worker.'.json'), true);
$raceFresh = $race->fresh();
$raceAudits = ReportExportAudit::where('report_export_id', $race->id)->count();
$check('C: both concurrent export jobs completed', count(array_filter($workerResults, fn (array $row): bool => ($row['status'] ?? null) === ReportExport::COMPLETED)) === 2, $workerResults, 'two COMPLETED workers');
$check('C: one authoritative completed artifact exists', $raceFresh?->status === ReportExport::COMPLETED && $raceFresh->storage_path !== null && $raceFresh->checksum !== null, $raceFresh?->toArray(), 'COMPLETED with storage_path/checksum');
$check('C: idempotent job emitted one start and one completion audit', $raceAudits === 2, $raceAudits, 2);

$expiry = $makeExport();
$manager = app(ReportExportManager::class);
$generated = $manager->generate($expiry->id);
$policy = app(ReportExportPolicy::class);
$before = $policy->download($owner, $generated);
$storageBefore = Storage::disk('local')->exists($generated->storage_path);
$generated->update(['expires_at' => now()->subMinute()]);
$expired = $manager->expire($generated);
$after = $policy->download($owner, $expired);
$storageAfter = $expired->storage_path ? Storage::disk('local')->exists($expired->storage_path) : false;
$check('D: completed export is downloadable before expiry', $before && $storageBefore, ['policy' => $before, 'storage' => $storageBefore], true);
$check('D: expiry-first path denies download and removes artifact', !$after && $expired->status === ReportExport::EXPIRED && !$storageAfter, ['policy' => $after, 'status' => $expired->status, 'storage' => $storageAfter], 'EXPIRED/denied/deleted');

$artifact = ['gate' => 'FLOW14_MARIADB_EXPORT_CONSISTENCY_PROBE_CD', 'pass' => !array_filter($results, fn (array $row): bool => !$row['pass']), 'environment' => ['driver' => DB::connection()->getDriverName(), 'database' => config('database.connections.mysql.database')], 'results' => $results];
file_put_contents($root.'/flow14_mariadb_export_consistency_probe_cd.json', json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($artifact['pass'] ? 0 : 1);
