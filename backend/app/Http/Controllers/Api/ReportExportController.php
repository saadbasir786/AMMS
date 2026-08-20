<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportExportRequest;
use App\Http\Resources\ReportExportResource;
use App\Jobs\GenerateReportExport;
use App\Models\Organization;
use App\Models\ReportExport;
use App\Models\ReportExportAudit;
use App\Policies\ReportExportPolicy;
use App\Reporting\Scopes\ReportingScopeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ReportExportController extends Controller
{
    public function store(ReportExportRequest $request)
    {
        $scope = new ReportingScopeResolver($request->user());
        $scope->requirePermission('reporting.exports.create');
        $organizationId = $request->user()->employee?->unit?->organization_id ?: Organization::query()->value('id');
        abort_unless($organizationId, 422, 'REPORTING_ORGANIZATION_REQUIRED');
        $filters = $request->filters();
        if ($request->filled('requesting_unit_id')) $scope->assertUnitFilter((int) $request->input('requesting_unit_id'));
        $export = ReportExport::create([
            'uuid' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'requested_by_user_id' => $request->user()->id,
            'report_type' => $request->reportType(),
            'format' => $request->exportFormat(),
            'filter_payload' => $filters->toArray(),
            'scope_descriptor' => ['unit_ids' => $scope->unitIds(), 'organization_wide' => $scope->allowsOrganizationBusiness(), 'user_id' => $request->user()->id],
            'status' => ReportExport::PENDING,
            'definition_version' => 'AMMS_REPORTING_V1',
            'requested_at' => now(),
        ]);
        ReportExportAudit::create(['report_export_id' => $export->id, 'actor_user_id' => $request->user()->id, 'action' => 'REPORT_EXPORT_REQUESTED', 'metadata' => ['report_type' => $export->report_type, 'format' => $export->format]]);
        GenerateReportExport::dispatch($export->id);
        return (new ReportExportResource($export))->additional(['success' => true, 'message' => 'درخواست تولید گزارش ثبت شد.']);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->isActive(), 401, 'UNAUTHORIZED');
        $exports = ReportExport::query()->where('requested_by_user_id', $request->user()->id)->latest()->paginate(20);
        return ReportExportResource::collection($exports)->additional(['success' => true, 'message' => 'صادرات گزارش‌های شما.']);
    }

    public function show(Request $request, ReportExport $export)
    {
        abort_unless(app(ReportExportPolicy::class)->view($request->user(), $export), 403, 'REPORT_EXPORT_ACCESS_DENIED');
        return (new ReportExportResource($export))->additional(['success' => true, 'message' => 'جزئیات صادرات گزارش.']);
    }

    public function download(Request $request, ReportExport $export)
    {
        abort_unless(app(ReportExportPolicy::class)->download($request->user(), $export), 403, 'REPORT_EXPORT_DOWNLOAD_DENIED');
        abort_unless(Storage::disk($export->storage_disk ?: 'local')->exists($export->storage_path), 404, 'REPORT_EXPORT_FILE_NOT_FOUND');
        ReportExportAudit::create(['report_export_id' => $export->id, 'actor_user_id' => $request->user()->id, 'action' => 'REPORT_EXPORT_DOWNLOADED', 'metadata' => ['file_size' => $export->file_size, 'checksum' => $export->checksum]]);
        return Storage::disk($export->storage_disk ?: 'local')->download($export->storage_path, $export->file_name, ['Content-Type' => $export->format === 'PDF' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
