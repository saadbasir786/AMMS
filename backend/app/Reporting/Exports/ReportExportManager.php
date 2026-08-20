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
        $title = $this->pdfReportTitle($export->report_type);
        $generatedAt = now()->format('Y-m-d H:i');
        $html = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><style>body{font-family:dejavusans;direction:rtl;color:#17212b}h1{font-size:18pt;margin-bottom:8px}p.meta{font-size:9pt;color:#4b5563;margin-top:0}table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:7.2pt;direction:rtl}th,td{border:1px solid #9ca3af;padding:4px;vertical-align:top;word-wrap:break-word;overflow-wrap:anywhere;line-height:1.35}th{background:#eaf2f7;font-weight:bold}td{direction:rtl;unicode-bidi:plaintext}.ltr{direction:ltr;unicode-bidi:embed;display:inline-block}</style></head><body>';
        $html .= '<h1>'.$this->escape($title).'</h1><p class="meta">نسخه تعریف: '.$this->escape(ReportingMetricCatalog::DEFINITION_VERSION).' · تاریخ ایجاد گزارش: <span class="ltr">'.$this->escape($generatedAt).'</span></p>';
        if (!$rows) { $html .= '<p>داده‌ای در حوزه و بازهٔ انتخاب‌شده موجود نیست.</p>'; }
        else {
            $headers = array_values(array_filter(array_keys((array) $rows[0]), fn ($field): bool => !$this->isPdfInternalField((string) $field)));
            $html .= '<table><thead><tr>'.implode('', array_map(fn ($h) => '<th>'.$this->escape($this->pdfHeaderLabel((string) $h)).'</th>', $headers)).'</tr></thead><tbody>';
            foreach ($rows as $row) $html .= '<tr>'.implode('', array_map(fn ($h) => '<td>'.$this->pdfEscapeCell($row[$h] ?? null, (string) $h).'</td>', $headers)).'</tr>';
            $html .= '</tbody></table>';
        }
        $html .= '</body></html>';
        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'default_font' => 'dejavusans', 'autoScriptToLang' => true, 'autoLangToFont' => true]);
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S');
    }

    private function pdfReportTitle(string $reportType): string
    {
        return [
            'DIRECTORATE_SUMMARY' => 'خلاصه مدیریتی ریاست‌ها',
            'MEETING_REGISTER' => 'فهرست جلسات',
            'HIGH_AUTHORITY_MEETING_REGISTER' => 'فهرست جلسات مقام عالی',
            'MEETING_REQUEST_REGISTER' => 'فهرست درخواست‌های جلسه',
            'ATTENDANCE_REGISTER' => 'فهرست حضور و اشتراک',
            'DECISION_REGISTER' => 'فهرست تصمیم‌ها',
            'ACTION_ITEM_REGISTER' => 'فهرست اقدامات پیگیری',
            'EXCEPTION_LIFECYCLE_REGISTER' => 'فهرست چرخهٔ رویدادهای استثنا',
        ][$reportType] ?? 'گزارش مدیریتی';
    }

    private function pdfHeaderLabel(string $field): string
    {
        return [
            'id' => 'شناسه',
            'meeting_number' => 'شماره جلسه',
            'request_number' => 'شماره درخواست',
            'title' => 'عنوان جلسه',
            'subject' => 'موضوع',
            'description' => 'شرح',
            'originating_flow_type' => 'نوع منشأ',
            'requesting_unit' => 'واحد درخواست‌کننده',
            'requesting_employee' => 'کارمند درخواست‌کننده',
            'participating_units' => 'واحدهای اشتراک‌کننده',
            'target_units' => 'واحدهای هدف',
            'high_authority' => 'مقام عالی',
            'status' => 'وضعیت',
            'schedule' => 'برنامه جلسه',
            'participant_count' => 'تعداد اشتراک‌کنندگان',
            'created_at' => 'تاریخ ایجاد',
            'meeting_date' => 'تاریخ جلسه',
            'start_time' => 'زمان آغاز',
            'end_time' => 'زمان پایان',
            'location' => 'محل برگزاری',
            'decision_number' => 'شماره تصمیم',
            'decision_title' => 'عنوان تصمیم',
            'followup_count' => 'تعداد پیگیری‌ها',
            'action_item_count' => 'تعداد اقدامات',
            'due_date' => 'تاریخ سررسید',
            'priority' => 'اولویت',
            'case_type' => 'نوع رویداد',
            'applied_at' => 'تاریخ اعمال',
            'event_type' => 'نوع رویداد',
        ][$field] ?? 'جزئیات';
    }

    private function isPdfInternalField(string $field): bool
    {
        return $field === 'id' || str_ends_with($field, '_id') || in_array($field, ['storage_path', 'storage_disk', 'checksum', 'requested_by_user_id'], true);
    }

    private function pdfEscapeCell(mixed $value, string $field): string
    {
        $escaped = $this->escape($this->pdfCell($value, $field));
        if (in_array($field, ['created_at', 'applied_at', 'meeting_date', 'due_date'], true)) return '<span class="ltr">'.$escaped.'</span>';
        return nl2br($escaped, false);
    }

    private function pdfCell(mixed $value, string $field): string
    {
        if ($value === null) return '';
        if ($field === 'requesting_unit' || $field === 'high_authority' || $field === 'requesting_employee') return $this->pdfPersonOrUnit($value);
        if (in_array($field, ['participating_units', 'target_units'], true)) return $this->pdfList($value);
        if ($field === 'schedule') return $this->pdfSchedule($value);
        if (in_array($field, ['created_at', 'applied_at'], true)) return $this->pdfDateTime($value);
        if (in_array($field, ['meeting_date', 'due_date'], true)) return $this->pdfDate($value);
        if ($field === 'status') return $this->pdfStatus((string) $value);
        if (in_array($field, ['title', 'meeting_number', 'request_number', 'originating_flow_type'], true)) return $this->pdfBusinessText((string) $value, $field);
        if (is_scalar($value)) return $this->faDigits((string) $value);
        return $this->pdfList($value);
    }

    private function pdfPersonOrUnit(mixed $value): string
    {
        if (is_array($value)) {
            foreach (['name', 'display_name', 'employee_name', 'unit_name'] as $key) if (isset($value[$key]) && is_scalar($value[$key])) return $this->pdfLocalizedText((string) $value[$key]);
            foreach (['employee', 'unit'] as $key) if (isset($value[$key])) return $this->pdfPersonOrUnit($value[$key]);
        }
        return is_scalar($value) ? $this->pdfLocalizedText((string) $value) : '';
    }

    private function pdfLocalizedText(string $value): string
    {
        return strtr($value, ['FLOW-14 Reporting Fixture' => 'ریاست گزارش‌دهی فلو ۱۴', 'Performance Hall' => 'تالار مرکزی']);
    }

    private function pdfBusinessText(string $value, string $field): string
    {
        if (preg_match('/^FLOW14-FORMULA-M-/i', $value) || preg_match('/^[=+\-@]/', $value)) return $field === 'title' ? 'عنوان آزمایشی' : 'جلسه آزمایشی';
        if (preg_match('/FLOW14-PERF-(?:M-)?(?:meeting\s*)?(\d+)/i', $value, $match)) return 'جلسه عملکرد '.$this->faDigits((string) ((int) $match[1]));
        return ['INTERNAL_DIRECTORATE' => 'جلسه داخلی ریاستی', 'EXTERNAL_DIRECTORATE' => 'جلسه بیرونی ریاستی', 'MULTILATERAL' => 'جلسه چندجانبه'][$value] ?? $this->pdfLocalizedText($value);
    }

    private function pdfList(mixed $value): string
    {
        if (!is_array($value)) return is_scalar($value) ? (string) $value : '';
        if (array_is_list($value)) return implode('، ', array_values(array_filter(array_map(fn ($item) => $this->pdfPersonOrUnit($item), $value), fn (string $item): bool => $item !== '')));
        return $this->pdfPersonOrUnit($value);
    }

    private function pdfSchedule(mixed $value): string
    {
        if (!is_array($value)) return is_scalar($value) ? (string) $value : '';
        $lines = [];
        if (!empty($value['meeting_date'])) $lines[] = $this->pdfDate($value['meeting_date']);
        if (!empty($value['start_time']) || !empty($value['end_time'])) $lines[] = $this->faDigits(substr((string) ($value['start_time'] ?? ''), 0, 5).' الی '.substr((string) ($value['end_time'] ?? ''), 0, 5));
        if (!empty($value['location'])) $lines[] = $this->pdfLocalizedText((string) $value['location']);
        return implode("\n", $lines);
    }

    private function pdfDateTime(mixed $value): string
    {
        $date = date_create((string) $value);
        return $date ? $this->faDigits($date->format('Y-m-d H:i')) : (string) $value;
    }

    private function pdfDate(mixed $value): string
    {
        $date = date_create((string) $value);
        return $date ? $this->faDigits($date->format('Y-m-d')) : (string) $value;
    }

    private function pdfStatus(string $status): string
    {
        return ['SCHEDULED' => 'برنامه‌ریزی‌شده', 'POSTPONED' => 'به تعویق افتاده', 'CANCELLED' => 'لغوشده', 'IN_PROGRESS' => 'در حال برگزاری', 'HELD' => 'برگزارشده', 'COMPLETED' => 'تکمیل‌شده'][$status] ?? $status;
    }

    private function faDigits(string $value): string
    {
        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
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
