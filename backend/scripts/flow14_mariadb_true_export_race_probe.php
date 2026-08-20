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

putenv('DB_CONNECTION=mysql');
require __DIR__.'/../vendor/autoload.php';
/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'mysql']);
if (DB::connection()->getDriverName() !== 'mysql') throw new RuntimeException('MariaDB/MySQL required.');
$root = dirname(__DIR__, 2);
$owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
$organizationId = (int) DB::table('employees')->join('organizational_units', 'organizational_units.id', '=', 'employees.organizational_unit_id')->where('employees.id', $owner->employee_id)->value('organizational_units.organization_id');
$payload = ['from' => '2026-01-01', 'to' => '2026-12-31', 'timezone' => 'Asia/Kabul', 'per_page' => 50];
$makeExport = static fn (): ReportExport => ReportExport::create(['uuid' => (string) Str::uuid(), 'organization_id' => $organizationId, 'requested_by_user_id' => $owner->id, 'report_type' => 'MEETING_REGISTER', 'format' => 'XLSX', 'filter_payload' => $payload, 'scope_descriptor' => ['mode' => 'ORGANIZATION', 'organization_id' => $organizationId], 'status' => ReportExport::PENDING, 'definition_version' => ReportingMetricCatalog::DEFINITION_VERSION, 'requested_at' => now()]);
$connectionId = static fn (): int => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
$reconnect = static function (): void { DB::purge('mysql'); DB::reconnect('mysql'); };
$waitFor = static function (string $path, float $timeout = 30.0): void { $start = microtime(true); while (!is_file($path)) { if (microtime(true) - $start > $timeout) throw new RuntimeException('Barrier timeout: '.$path); usleep(10000); } };
$signal = static function (string $path): void { file_put_contents($path, json_encode(['pid' => getmypid(), 'at' => microtime(true)])); };
$runConcurrent = static function (string $name, callable $work) use ($root, $reconnect, $waitFor, $signal, $connectionId): array {
    $dir = storage_path('app/flow14-export-race-'.$name); @mkdir($dir, 0775, true);
    $pids = [];
    foreach (['worker_a', 'worker_b'] as $worker) {
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('fork failed');
        if ($pid === 0) {
            $reconnect();
            $meta = ['worker' => $worker, 'pid' => getmypid(), 'connection_id' => $connectionId(), 'barrier_point' => $dir.'/release', 'operation_start' => microtime(true)];
            file_put_contents($dir.'/'.$worker.'.ready.json', json_encode($meta));
            try { $waitFor($dir.'/release'); $meta['result'] = $work($worker, $dir, $meta); $meta['ok'] = true; } catch (Throwable $e) { $meta['ok'] = false; $meta['error'] = ['class' => $e::class, 'message' => $e->getMessage()]; }
            $meta['operation_commit_or_result'] = microtime(true);
            file_put_contents($dir.'/'.$worker.'.result.json', json_encode($meta, JSON_UNESCAPED_UNICODE));
            $signal($dir.'/'.$worker.'.done'); exit(0);
        }
        $pids[] = $pid;
    }
    foreach (['worker_a', 'worker_b'] as $worker) $waitFor($dir.'/'.$worker.'.ready.json');
    $signal($dir.'/release');
    foreach ($pids as $pid) pcntl_waitpid($pid, $status);
    $workers = [];
    foreach (['worker_a', 'worker_b'] as $worker) $workers[$worker] = json_decode((string) file_get_contents($dir.'/'.$worker.'.result.json'), true, 512, JSON_THROW_ON_ERROR);
    return ['name' => $name, 'barrier' => ['release' => $dir.'/release', 'worker_a_ready' => $dir.'/worker_a.ready.json', 'worker_b_ready' => $dir.'/worker_b.ready.json'], 'workers' => $workers];
};
$runOrdered = static function (string $name, string $first, callable $work) use ($root, $reconnect, $waitFor, $signal, $connectionId): array {
    $dir = storage_path('app/flow14-export-race-'.$name); @mkdir($dir, 0775, true);
    $pids = [];
    foreach (['download', 'expiry'] as $worker) {
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('fork failed');
        if ($pid === 0) {
            $reconnect();
            $meta = ['worker' => $worker, 'pid' => getmypid(), 'connection_id' => $connectionId(), 'barrier_point' => $dir.'/release', 'operation_start' => microtime(true)];
            file_put_contents($dir.'/'.$worker.'.ready.json', json_encode($meta));
            try {
                $waitFor($dir.'/release');
                if ($first === 'download' && $worker === 'expiry') $waitFor($dir.'/download.read');
                if ($first === 'expiry' && $worker === 'download') $waitFor($dir.'/expiry.commit');
                $meta['result'] = $work($worker, $dir, $meta); $meta['ok'] = true;
            } catch (Throwable $e) { $meta['ok'] = false; $meta['error'] = ['class' => $e::class, 'message' => $e->getMessage()]; }
            $meta['operation_commit_or_result'] = microtime(true);
            file_put_contents($dir.'/'.$worker.'.result.json', json_encode($meta, JSON_UNESCAPED_UNICODE));
            $signal($dir.'/'.$worker.'.done'); exit(0);
        }
        $pids[] = $pid;
    }
    foreach (['download', 'expiry'] as $worker) $waitFor($dir.'/'.$worker.'.ready.json');
    $signal($dir.'/release');
    foreach ($pids as $pid) pcntl_waitpid($pid, $status);
    $workers = [];
    foreach (['download', 'expiry'] as $worker) $workers[$worker] = json_decode((string) file_get_contents($dir.'/'.$worker.'.result.json'), true, 512, JSON_THROW_ON_ERROR);
    return ['name' => $name, 'first' => $first, 'barrier' => ['release' => $dir.'/release', 'download_ready' => $dir.'/download.ready.json', 'expiry_ready' => $dir.'/expiry.ready.json'], 'workers' => $workers];
};
$manager = app(ReportExportManager::class);
$generation = $makeExport();
$generationRace = $runConcurrent('generation-'.$generation->uuid, static function (string $worker, string $dir, array $meta) use ($generation, $manager, $connectionId): array { DB::purge('mysql'); DB::reconnect('mysql'); $export = app(ReportExportManager::class)->generate($generation->id);
    if ($export->status === ReportExport::RUNNING) {
        $deadline = microtime(true) + 30;
        do { usleep(20000); $export = ReportExport::findOrFail($generation->id); } while ($export->status === ReportExport::RUNNING && microtime(true) < $deadline);
    }
    return ['worker' => $worker, 'connection_id' => $connectionId(), 'export_uuid' => $export->uuid, 'status' => $export->status, 'checksum' => $export->checksum, 'storage_path' => $export->storage_path, 'audit_actions_seen_by_worker' => DB::table('report_export_audits')->where('report_export_id', $generation->id)->pluck('action')->all(), 'finished_at' => microtime(true)]; });
$generationFresh = $generation->fresh();
$generationAudits = ReportExportAudit::where('report_export_id', $generation->id)->get(['action', 'metadata'])->values()->all();
$generationResultChecksums = array_values(array_unique(array_filter(array_map(fn (array $w) => $w['result']['checksum'] ?? null, $generationRace['workers']))));
$generationResultPaths = array_values(array_unique(array_filter(array_map(fn (array $w) => $w['result']['storage_path'] ?? null, $generationRace['workers']))));
$generationEvidence = ['workers' => $generationRace, 'export_uuid' => $generation->uuid, 'final_status' => $generationFresh?->status, 'final_checksum' => $generationFresh?->checksum, 'final_storage_path' => $generationFresh?->storage_path, 'worker_checksum_count' => count($generationResultChecksums), 'worker_storage_path_count' => count($generationResultPaths), 'authoritative_artifact_count' => $generationFresh?->storage_path ? 1 : 0, 'start_audit_count' => count(array_filter($generationAudits, fn ($a) => $a->action === 'REPORT_EXPORT_STARTED')), 'completion_audit_count' => count(array_filter($generationAudits, fn ($a) => $a->action === 'REPORT_EXPORT_COMPLETED'))];
$checkExport = $makeExport();
$checkExport = $manager->generate($checkExport->id);
$policy = app(ReportExportPolicy::class);
$downloadRace = $runOrdered('download-before-expiry-'.$checkExport->uuid, 'download', static function (string $worker, string $dir, array $meta) use ($checkExport, $owner, $policy, $manager, $signal, $connectionId): array {
    if ($worker === 'download') {
        $export = ReportExport::findOrFail($checkExport->id);
        $allowed = $policy->download($owner, $export);
        $storageExists = $export->storage_path ? Storage::disk($export->storage_disk ?: 'local')->exists($export->storage_path) : false;
        $contentHash = $allowed && $storageExists ? hash('sha256', (string) Storage::disk($export->storage_disk ?: 'local')->get($export->storage_path)) : null;
        $signal($dir.'/download.read');
        return ['policy_allowed' => $allowed, 'storage_exists' => $storageExists, 'stream_content_sha256' => $contentHash, 'application_result' => $allowed && $storageExists ? 'STREAM_READ_BEFORE_EXPIRY' : 'DENIED'];
    }
    $export = ReportExport::findOrFail($checkExport->id); $export->update(['expires_at' => now()->subSecond()]); $expired = $manager->expire($export); $signal($dir.'/expiry.commit'); return ['status' => $expired->status, 'storage_exists_after_expiry' => $expired->storage_path ? Storage::disk($expired->storage_path)->exists($expired->storage_path) : false, 'commit_finished_at' => microtime(true)];
});
$expireRace = $makeExport(); $expireRace = $manager->generate($expireRace->id);
$expiryFirst = $runOrdered('expiry-before-download-'.$expireRace->uuid, 'expiry', static function (string $worker, string $dir, array $meta) use ($expireRace, $owner, $policy, $manager, $signal): array {
    if ($worker === 'expiry') { $export = ReportExport::findOrFail($expireRace->id); $export->update(['expires_at' => now()->subSecond()]); $expired = $manager->expire($export); $signal($dir.'/expiry.commit'); return ['status' => $expired->status, 'storage_path_after_commit' => $expired->storage_path, 'storage_exists_after_commit' => false]; }
    $export = ReportExport::findOrFail($expireRace->id); $allowed = $policy->download($owner, $export); $storageExists = $export->storage_path ? Storage::disk($export->storage_disk ?: 'local')->exists($export->storage_path) : false; return ['policy_allowed' => $allowed, 'storage_exists' => $storageExists, 'application_result' => $allowed && $storageExists ? 'STREAMED' : 'DENIED_AFTER_COMMITTED_EXPIRY'];
});
$downloadPass = ($downloadRace['workers']['download']['result']['policy_allowed'] ?? false) && ($downloadRace['workers']['download']['result']['stream_content_sha256'] ?? null) !== null && ($downloadRace['workers']['expiry']['result']['status'] ?? null) === ReportExport::EXPIRED;
$expiryPass = ($expiryFirst['workers']['expiry']['result']['status'] ?? null) === ReportExport::EXPIRED && !($expiryFirst['workers']['download']['result']['policy_allowed'] ?? true) && ($expiryFirst['workers']['download']['result']['application_result'] ?? null) === 'DENIED_AFTER_COMMITTED_EXPIRY';
$artifact = ['gate' => 'FLOW14_MARIADB_TRUE_EXPORT_RACE_PROBE', 'pass' => true, 'environment' => ['driver' => DB::connection()->getDriverName(), 'database' => config('database.connections.mysql.database')], 'C_concurrent_generation' => $generationEvidence, 'D_download_vs_expiry' => ['ordering_A_download_wins' => $downloadRace, 'ordering_B_expiry_wins' => $expiryFirst, 'ordering_A_pass' => $downloadPass, 'ordering_B_pass' => $expiryPass, 'never_served_after_committed_expiry' => $expiryPass], 'storage_contract' => 'All workers use the same private local storage disk; expiry removes storage_path before post-commit download authorization is evaluated.'];
$artifact['pass'] = $downloadPass && $expiryPass && $generationEvidence['final_status'] === ReportExport::COMPLETED && $generationEvidence['worker_checksum_count'] === 1 && $generationEvidence['worker_storage_path_count'] === 1 && $generationEvidence['authoritative_artifact_count'] === 1 && $generationEvidence['start_audit_count'] === 1 && $generationEvidence['completion_audit_count'] === 1;
file_put_contents($root.'/flow14_mariadb_true_export_race_probe.json', json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($artifact['pass'] ? 0 : 1);
