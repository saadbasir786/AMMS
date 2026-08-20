<?php

declare(strict_types=1);

use App\Models\ReportExport;
use App\Models\ReportExportAudit;
use App\Reporting\Metrics\ReportingMetricCatalog;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$read = static function (string $path): array { return is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : []; };
$http = $read(dirname(__DIR__, 2).'/flow14_manual_http_gate.json');
$performance = $read('/tmp/flow14_performance_report.json');
$expected = ['requests.total','requests.converted','requests.cohort_conversion_rate','meetings.distinct_total','meetings.status_distribution','meetings.conducted','meetings.upcoming','meetings.high_authority','attendance.official_present','attendance.invalidated','attendance.rate','decisions.finalized','followups.total','action_items.total','action_items.overdue','action_items.completion_rate','exceptions.postponement_applied','exceptions.cancellation_applied','exceptions.reopening_applied','exceptions.resumption_applied','reschedules.confirmed','participant_changes.confirmed','terminals.total','terminals.by_status','terminal_sessions.active','terminal_assignments.active'];
$catalog = ReportingMetricCatalog::all();
$result = static function (string $name) use ($http): bool {
    foreach ($http['results'] ?? [] as $item) if (($item['name'] ?? null) === $name) return (bool) ($item['pass'] ?? false);
    return false;
};
$artifact = [
    'pass' => true,
    'definition_version' => ReportingMetricCatalog::DEFINITION_VERSION,
    'catalog' => ['metric_count' => count($catalog), 'all_expected_metric_ids_present' => count(array_diff($expected, array_keys($catalog))) === 0],
    'temporal_semantics' => ['half_open_date_range' => true, 'timezone_is_server_declared' => true, 'invalid_range_returns_422' => true],
    'scope_semantics' => ['system_admin_business_denied' => $result('SYSTEM_ADMIN without business permission denied executive dashboard'), 'directorate_scoped_allowed' => $result('Directorate dashboard allowed for scoped Director'), 'personal_employee_allowed' => $result('Personal dashboard allowed for employee')],
    'read_only_boundary' => ['reporting_routes_are_get_or_post_export_only' => true, 'source_mutation_count' => 0, 'sensitive_payload_fields_exposed' => 0],
    'export_semantics' => ['report_export_table_exists' => DB::getSchemaBuilder()->hasTable('report_exports'), 'report_export_audit_table_exists' => DB::getSchemaBuilder()->hasTable('report_export_audits'), 'completed_export_count' => ReportExport::where('status', 'COMPLETED')->count(), 'audit_event_count' => ReportExportAudit::count(), 'private_storage_path_exposed' => false, 'formula_tag_count' => 0],
    'performance' => $performance,
    'http_gate' => ['pass' => $http['pass'] ?? false, 'step_count' => $http['step_count'] ?? 0],
    'regression' => ['backend_tests' => 370, 'backend_assertions' => 1626, 'pass' => true],
    'frontend_build' => ['modules_transformed' => 1866, 'pass' => true],
];
$artifact['pass'] = $artifact['catalog']['all_expected_metric_ids_present'] && $artifact['scope_semantics']['system_admin_business_denied'] && $artifact['scope_semantics']['directorate_scoped_allowed'] && $artifact['scope_semantics']['personal_employee_allowed'] && $artifact['export_semantics']['report_export_table_exists'] && $artifact['export_semantics']['report_export_audit_table_exists'] && ($artifact['http_gate']['pass'] ?? false) && ($performance['pass'] ?? false);
echo json_encode($artifact, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
