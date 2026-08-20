<?php

declare(strict_types=1);

use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\Organization;
use App\Models\ReportExport;
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
$root = dirname(__DIR__, 2);
$owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
$other = User::where('email', 'tech.director@example.test')->firstOrFail();
$unitId = (int) DB::table('organizational_units')->where('code', 'FLOW14-REPORTING')->value('id');
$organizationId = (int) DB::table('organizational_units')->where('id', $unitId)->value('organization_id');
$now = now();
$results = [];
$check = static function (string $name, bool $pass, mixed $actual = null, mixed $expected = null) use (&$results): void { $results[] = ['name' => $name, 'pass' => $pass, 'actual' => $actual, 'expected' => $expected]; };
$payload = ['from' => '2026-01-01', 'to' => '2026-12-31', 'timezone' => 'Asia/Kabul', 'involved_unit_id' => $unitId, 'per_page' => 50];

$request = MeetingRequest::create([
    'request_number' => 'FLOW14-FORMULA-R-'.Str::upper(Str::random(8)), 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $unitId,
    'created_by' => $owner->id, 'subject' => '=HYPERLINK("https://example.invalid","x")', 'purpose' => 'FORMULA_SECURITY',
    'description' => '+SUM(1,1)', 'priority' => 'NORMAL', 'preferred_date' => $now->toDateString(), 'preferred_start_time' => '10:00:00',
    'estimated_duration_minutes' => 60, 'proposed_location' => '@malicious', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id,
]);
$meeting = Meeting::create([
    'meeting_request_id' => $request->id, 'meeting_number' => 'FLOW14-FORMULA-M-'.Str::upper(Str::random(8)),
    'title' => '=HYPERLINK("https://example.invalid","x")', 'description' => '@formula', 'organizing_unit_id' => $unitId,
    'coordinator_id' => $owner->id, 'chairperson_employee_id' => $owner->employee_id, 'meeting_date' => $now->toDateString(),
    'start_time' => '10:00:00', 'end_time' => '11:00:00', 'location' => '+SUM(1,1)', 'agenda' => '-1+1', 'status' => 'SCHEDULED',
]);
DB::table('meeting_units')->insert(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unitId, 'role' => 'PARTICIPATING', 'created_at' => $now, 'updated_at' => $now]);
$sourceBefore = [
    'request' => DB::table('meeting_requests')->where('id', $request->id)->first(),
    'meeting' => DB::table('meetings')->where('id', $meeting->id)->first(),
    'units' => DB::table('meeting_units')->where('meeting_id', $meeting->id)->get()->all(),
];
$sourceHashBefore = hash('sha256', serialize($sourceBefore));

$makeExport = static fn (string $format): ReportExport => ReportExport::create([
    'uuid' => (string) Str::uuid(), 'organization_id' => $organizationId, 'requested_by_user_id' => $owner->id,
    'report_type' => 'MEETING_REGISTER', 'format' => $format, 'filter_payload' => $payload,
    'scope_descriptor' => ['mode' => 'ORGANIZATION', 'organization_id' => $organizationId], 'status' => ReportExport::PENDING,
    'definition_version' => ReportingMetricCatalog::DEFINITION_VERSION, 'requested_at' => now(),
]);
$manager = app(ReportExportManager::class);
$xlsx = $manager->generate($makeExport('XLSX')->id);
$xlsxPath = Storage::disk('local')->path($xlsx->storage_path);
$zip = new ZipArchive();
$zip->open($xlsxPath);
$sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
$zip->close();
$check('XLSX package opens', $zip->open($xlsxPath) === true, null, true);
$check('XLSX contains no formula tags', !str_contains($sheetXml, '<f>'), null, true);
$check('XLSX neutralizes HYPERLINK formula-like value', str_contains($sheetXml, 'HYPERLINK') && str_contains($sheetXml, "'=HYPERLINK"), $sheetXml, 'apostrophe-prefixed inert text');
$check('XLSX checksum matches stored artifact', hash_file('sha256', $xlsxPath) === $xlsx->checksum, hash_file('sha256', $xlsxPath), $xlsx->checksum);
$check('XLSX contains Persian/report values', str_contains($sheetXml, 'FLOW14') && str_contains($sheetXml, 'SCHEDULED'), null, true);

$pdf = $manager->generate($makeExport('PDF')->id);
$pdfPath = Storage::disk('local')->path($pdf->storage_path);
$pdfImage = $root.'/flow14_pdf_visual_gate_page1.png';
$renderCommand = sprintf('pdftoppm -f 1 -l 1 -singlefile -png -r 180 %s %s 2>&1', escapeshellarg($pdfPath), escapeshellarg(substr($pdfImage, 0, -4)));
exec($renderCommand, $renderOutput, $renderStatus);
$check('PDF parses and has pages', is_file($pdfPath) && filesize($pdfPath) > 100 && $renderStatus === 0, ['path' => $pdfPath, 'render_status' => $renderStatus], true);
$check('PDF visual gate produced a rendered page', is_file($pdfImage) && filesize($pdfImage) > 1000, $pdfImage, true);
$check('PDF checksum matches stored artifact', hash_file('sha256', $pdfPath) === $pdf->checksum, hash_file('sha256', $pdfPath), $pdf->checksum);

$policy = app(ReportExportPolicy::class);
$check('Owner can view own export', $policy->view($owner, $xlsx), true, true);
$check('Other user cannot view owner export', !$policy->view($other, $xlsx), false, false);
$check('Other user cannot download owner export', !$policy->download($other, $xlsx), false, false);
$check('Owner can download unexpired export', $policy->download($owner, $xlsx), true, true);

$sourceAfter = [
    'request' => DB::table('meeting_requests')->where('id', $request->id)->first(),
    'meeting' => DB::table('meetings')->where('id', $meeting->id)->first(),
    'units' => DB::table('meeting_units')->where('meeting_id', $meeting->id)->get()->all(),
];
$sourceHashAfter = hash('sha256', serialize($sourceAfter));
$check('source rows remain immutable during dashboard/register/export reads', $sourceHashBefore === $sourceHashAfter, $sourceHashAfter, $sourceHashBefore);

$artifact = ['gate' => 'FLOW14_EXPORT_STRICT_GATE', 'pass' => !array_filter($results, fn (array $row): bool => !$row['pass']), 'source_immutability' => ['before_sha256' => $sourceHashBefore, 'after_sha256' => $sourceHashAfter, 'pass' => $sourceHashBefore === $sourceHashAfter], 'artifacts' => ['xlsx_export_id' => $xlsx->id, 'pdf_export_id' => $pdf->id, 'xlsx_sha256' => $xlsx->checksum, 'pdf_sha256' => $pdf->checksum, 'pdf_render' => $pdfImage], 'results' => $results];
file_put_contents($root.'/flow14_export_strict_gate.json', json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($artifact['pass'] ? 0 : 1);
