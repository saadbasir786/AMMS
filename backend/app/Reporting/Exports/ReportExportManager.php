<?php

declare(strict_types=1);

namespace App\Reporting\Exports;

use App\Http\Resources\ReportingActionItemResource;
use App\Http\Resources\ReportingAttendanceResource;
use App\Http\Resources\ReportingDecisionResource;
use App\Http\Resources\ReportingExceptionResource;
use App\Http\Resources\ReportingMeetingResource;
use App\Http\Resources\ReportingRequestResource;
use App\Models\ReportExport;
use App\Models\ReportExportAudit;
use App\Models\User;
use App\Reporting\Filters\ReportingFilter;
use App\Reporting\Metrics\ReportingMetricCatalog;
use App\Reporting\Queries\ReportingQueryService;
use App\Reporting\Scopes\ReportingScopeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ReportExportManager
{
    public function __construct(private readonly ReportingQueryService $queries) {}

    public function generate(int $exportId): ReportExport
    {
        if (DB::connection()->getDriverName() !== 'mysql') return $this->generateLocked($exportId);
        $claimed = DB::table('report_exports')->where('id', $exportId)->where('status', ReportExport::PENDING)->update(['status' => ReportExport::RUNNING, 'started_at' => now(), 'error_code' => null]);
        if (!$claimed) {
            $current = ReportExport::findOrFail($exportId);
            if ($current->status === ReportExport::RUNNING) return $current;
            return $this->generateLocked($exportId);
        }
        $claimedExport = ReportExport::findOrFail($exportId);
        ReportExportAudit::create(['report_export_id' => $claimedExport->id, 'actor_user_id' => $claimedExport->requested_by_user_id, 'action' => 'REPORT_EXPORT_STARTED', 'metadata' => ['format' => $claimedExport->format, 'report_type' => $claimedExport->report_type]]);
        return $this->generateLocked($exportId, true);
    }

    private function generateLocked(int $exportId, bool $claimed = false): ReportExport
    {
        return DB::transaction(function () use ($exportId, $claimed): ReportExport {
            $export = ReportExport::query()->lockForUpdate()->findOrFail($exportId);
            if ($export->status === ReportExport::COMPLETED && $export->storage_path) return $export;
            if ($export->status === ReportExport::RUNNING && !$claimed) return $export;
            if ($export->status === ReportExport::EXPIRED) return $export;

            if (!$claimed) {
                $export->update(['status' => ReportExport::RUNNING, 'started_at' => now(), 'error_code' => null]);
                $this->audit($export, 'REPORT_EXPORT_STARTED', ['format' => $export->format, 'report_type' => $export->report_type]);
            }

            try {
                $owner = $export->requestedBy()->firstOrFail();
                $scope = new ReportingScopeResolver($owner);
                $scope->requirePermission('reporting.exports.create');
                $filters = ReportingFilter::fromRequest(Request::create('/', 'GET', $export->filter_payload ?: []));
                $rows = $this->rows($scope, $filters, $export->report_type);
                $content = strtoupper($export->format) === 'PDF' ? $this->pdf($rows, $export) : $this->xlsx($rows, $export);
                $extension = strtoupper($export->format) === 'PDF' ? 'pdf' : 'xlsx';
                $path = 'report-exports/'.$export->uuid.'.'.$extension;
                Storage::disk('local')->put($path, $content);
                $checksum = hash('sha256', $content);
                $export->update([
                    'status' => ReportExport::COMPLETED,
                    'completed_at' => now(),
                    'expires_at' => now()->addHours((int) config('amms.reporting.export_ttl_hours', 24)),
                    'storage_disk' => 'local',
                    'storage_path' => $path,
                    'file_name' => $this->fileName($export, $extension),
                    'file_size' => strlen($content),
                    'checksum' => $checksum,
                ]);
                $this->audit($export, 'REPORT_EXPORT_COMPLETED', ['row_count' => count($rows), 'checksum' => $checksum, 'file_size' => strlen($content)]);
                return $export->fresh();
            } catch (\Throwable $exception) {
                $export->update(['status' => ReportExport::FAILED, 'error_code' => 'REPORT_EXPORT_GENERATION_FAILED']);
                $this->audit($export, 'REPORT_EXPORT_FAILED', ['error_code' => 'REPORT_EXPORT_GENERATION_FAILED']);
                throw $exception;
            }
        });
    }

    public function expire(ReportExport $export): ReportExport
    {
        return DB::transaction(function () use ($export): ReportExport {
            $locked = ReportExport::query()->lockForUpdate()->findOrFail($export->id);
            if ($locked->status !== ReportExport::COMPLETED || !$locked->expires_at || $locked->expires_at->isFuture()) return $locked;
            if ($locked->storage_path) Storage::disk($locked->storage_disk ?: 'local')->delete($locked->storage_path);
            $locked->update(['status' => ReportExport::EXPIRED, 'storage_path' => null, 'storage_disk' => null]);
            $this->audit($locked, 'REPORT_EXPORT_EXPIRED', []);
            return $locked->fresh();
        });
    }

    private function rows(ReportingScopeResolver $scope, ReportingFilter $filters, string $type): array
    {
        if ($type === 'DIRECTORATE_SUMMARY') return $this->queries->dashboard($scope, $filters)['cards'];
        if ($type === 'HIGH_AUTHORITY_MEETING_REGISTER') {
            $filters = ReportingFilter::fromRequest(request()->create('/', 'GET', array_merge($filters->toArray(), ['high_authority_only' => true])));
            $type = 'MEETING_REGISTER';
        }
        $resource = match ($type) {
            'MEETING_REGISTER' => ReportingMeetingResource::collection($this->queries->meetings($scope, $filters))->resolve(),
            'MEETING_REQUEST_REGISTER' => ReportingRequestResource::collection($this->queries->requests($scope, $filters))->resolve(),
            'ATTENDANCE_REGISTER' => ReportingAttendanceResource::collection($this->queries->attendance($scope, $filters))->resolve(),
            'DECISION_REGISTER' => ReportingDecisionResource::collection($this->queries->decisions($scope, $filters))->resolve(),
            'ACTION_ITEM_REGISTER' => ReportingActionItemResource::collection($this->queries->actionItems($scope, $filters))->resolve(),
            'EXCEPTION_LIFECYCLE_REGISTER' => ReportingExceptionResource::collection($this->queries->exceptions($scope, $filters))->resolve(),
            default => throw new HttpException(422, 'UNSUPPORTED_REPORT_TYPE'),
        };
        return array_values($resource);
    }

    private function pdf(array $rows, ReportExport $export): string
    {
        $html = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>body{font-family:dejavusans;direction:rtl}h1{font-size:18pt}table{width:100%;border-collapse:collapse;font-size:8pt}th,td{border:1px solid #bbb;padding:5px;vertical-align:top}th{background:#eaf2f7}</style></head><body>';
        $html .= '<h1>گزارش '.$this->escape($export->report_type).'</h1><p>نسخه تعریف: '.self::escape(ReportingMetricCatalog::DEFINITION_VERSION).' · ایجاد: '.now()->toIso8601String().'</p>';
        if (!$rows) { $html .= '<p>داده‌ای در حوزه و بازهٔ انتخاب‌شده موجود نیست.</p>'; }
        else {
            $headers = array_keys((array) $rows[0]);
            $html .= '<table><thead><tr>'.implode('', array_map(fn ($h) => '<th>'.$this->escape((string) $h).'</th>', $headers)).'</tr></thead><tbody>';
            foreach ($rows as $row) $html .= '<tr>'.implode('', array_map(fn ($h) => '<td>'.$this->escape($this->cell($row[$h] ?? null)).'</td>', $headers)).'</tr>';
            $html .= '</tbody></table>';
        }
        $html .= '</body></html>';
        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'default_font' => 'dejavusans', 'autoScriptToLang' => true, 'autoLangToFont' => true]);
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S');
    }

    private function xlsx(array $rows, ReportExport $export): string
    {
        $headers = $rows ? array_keys((array) $rows[0]) : ['message'];
        if (!$rows) $rows = [['message' => 'No data']];
        $sheetRows = [$headers];
        foreach ($rows as $row) $sheetRows[] = array_map(fn ($header) => $this->cell($row[$header] ?? null), $headers);
        $tmp = storage_path('app/report-export-'.Str::uuid());
        mkdir($tmp.'/xl/worksheets', 0775, true);
        mkdir($tmp.'/xl/_rels', 0775, true);
        mkdir($tmp.'/_rels', 0775, true);
        file_put_contents($tmp.'/[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        file_put_contents($tmp.'/_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        file_put_contents($tmp.'/xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        file_put_contents($tmp.'/xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($sheetRows as $rowNumber => $row) { $xml .= '<row r="'.($rowNumber + 1).'">'; foreach (array_values($row) as $column => $value) { $ref = $this->column($column).($rowNumber + 1); $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escapeXml((string) $this->safeCell($value)).'</t></is></c>'; } $xml .= '</row>'; }
        $xml .= '</sheetData></worksheet>';
        file_put_contents($tmp.'/xl/worksheets/sheet1.xml', $xml);
        $zip = new \ZipArchive();
        $zipPath = $tmp.'.xlsx';
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) if ($file->isFile()) $zip->addFile($file->getPathname(), substr($file->getPathname(), strlen($tmp) + 1));
        $zip->close();
        $content = file_get_contents($zipPath);
        foreach (glob($tmp.'*') ?: [] as $path) is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        return $content;
    }

    private function audit(ReportExport $export, string $action, array $metadata): void
    {
        ReportExportAudit::create(['report_export_id' => $export->id, 'actor_user_id' => $export->requested_by_user_id, 'action' => $action, 'metadata' => $metadata]);
    }

    private function fileName(ReportExport $export, string $extension): string { return 'AMMS-'.$export->report_type.'-'.now()->format('Ymd-His').'.'.$extension; }
    private function cell(mixed $value): string { return is_scalar($value) || $value === null ? (string) ($value ?? '') : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
    private function safeCell(mixed $value): string { $text = $this->cell($value); return in_array($text[0] ?? '', ['=', '+', '-', '@'], true) ? "'".$text : $text; }
    private function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    private function escapeXml(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8'); }
    private function column(int $index): string { $out = ''; do { $out = chr(65 + ($index % 26)).$out; $index = intdiv($index, 26) - 1; } while ($index >= 0); return $out; }
    private function removeDirectory(string $directory): void { foreach (glob($directory.'/*') ?: [] as $path) is_dir($path) ? $this->removeDirectory($path) : @unlink($path); @rmdir($directory); }
}
