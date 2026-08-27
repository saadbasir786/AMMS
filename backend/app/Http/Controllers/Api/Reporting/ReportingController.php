<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reporting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\ReportingFilterRequest;
use App\Http\Resources\ReportingActionItemResource;
use App\Http\Resources\ReportingAttendanceResource;
use App\Http\Resources\ReportingDecisionResource;
use App\Http\Resources\ReportingExceptionResource;
use App\Http\Resources\ReportingMeetingResource;
use App\Http\Resources\ReportingRequestResource;
use App\Reporting\Queries\ReportingQueryService;
use App\Reporting\Scopes\ReportingScopeResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportingController extends Controller
{
    public function __construct(private readonly ReportingQueryService $queries) {}

    public function executive(ReportingFilterRequest $request): JsonResponse
    {
        return $this->dashboard($request, 'reporting.dashboard.executive');
    }

    public function directorate(ReportingFilterRequest $request): JsonResponse
    {
        return $this->dashboard($request, 'reporting.dashboard.directorate');
    }

    public function personal(ReportingFilterRequest $request): JsonResponse
    {
        return $this->dashboard($request, 'reporting.dashboard.personal');
    }

    public function authority(ReportingFilterRequest $request): JsonResponse
    {
        return $this->dashboard($request, 'reporting.dashboard.authority');
    }

    public function technical(Request $request): JsonResponse
    {
        $scope = new ReportingScopeResolver($request->user());
        $scope->requirePermission('reporting.dashboard.technical');
        return response()->json(['success' => true, 'data' => $this->queries->technical($scope), 'message' => 'گزارش تخنیکی.']);
    }

    public function meetings(ReportingFilterRequest $request)
    {
        $scope = $this->scope($request, 'reporting.meetings.view');
        return ReportingMeetingResource::collection($this->queries->meetings($scope, $request->filters()))
            ->additional(['success' => true, 'message' => 'ثبت جلسات.', 'definition_version' => 'AMMS_REPORTING_V1']);
    }

    public function requests(ReportingFilterRequest $request)
    {
        $scope = $this->scope($request, 'reporting.requests.view');
        return ReportingRequestResource::collection($this->queries->requests($scope, $request->filters()))
            ->additional(['success' => true, 'message' => 'ثبت درخواست‌ها.', 'definition_version' => 'AMMS_REPORTING_V1']);
    }

    public function attendance(ReportingFilterRequest $request)
    {
        $scope = $this->scope($request, $request->user()->can('reporting.attendance.detail') ? 'reporting.attendance.detail' : 'reporting.attendance.summary');
        return ReportingAttendanceResource::collection($this->queries->attendance($scope, $request->filters()))
            ->additional(['success' => true, 'message' => 'ثبت حضور رسمی.', 'definition_version' => 'AMMS_REPORTING_V1']);
    }

    public function decisions(ReportingFilterRequest $request)
    {
        $scope = $this->scope($request, 'reporting.decisions.view');
        return ReportingDecisionResource::collection($this->queries->decisions($scope, $request->filters()))
            ->additional(['success' => true, 'message' => 'ثبت تصمیم‌های نهایی.', 'definition_version' => 'AMMS_REPORTING_V1']);
    }

    public function actionItems(ReportingFilterRequest $request)
    {
        $scope = $this->scope($request, 'reporting.action-items.view');
        return ReportingActionItemResource::collection($this->queries->actionItems($scope, $request->filters()))
            ->additional(['success' => true, 'message' => 'ثبت اقدامات.', 'definition_version' => 'AMMS_REPORTING_V1']);
    }

    public function exceptions(ReportingFilterRequest $request)
    {
        $scope = $this->scope($request, 'reporting.exceptions.view');
        return ReportingExceptionResource::collection($this->queries->exceptions($scope, $request->filters()))
            ->additional(['success' => true, 'message' => 'ثبت رویدادهای استثنایی.', 'definition_version' => 'AMMS_REPORTING_V1']);
    }

    public function highAuthorityMeetings(ReportingFilterRequest $request)
    {
        $request->merge(['high_authority_only' => true]);
        $scope = $this->scope($request, 'reporting.meetings.view');
        return ReportingMeetingResource::collection($this->queries->meetings($scope, $request->filters()))
            ->additional(['success' => true, 'message' => 'ثبت جلسات مقام عالی.', 'definition_version' => 'AMMS_REPORTING_V1']);
    }

    private function dashboard(ReportingFilterRequest $request, string $permission): JsonResponse
    {
        $scope = $this->scope($request, $permission);
        return response()->json(['success' => true, 'data' => $this->queries->dashboard($scope, $request->filters()), 'message' => 'گزارش مدیریتی.']);
    }

    private function scope(ReportingFilterRequest|Request $request, string $permission): ReportingScopeResolver
    {
        $scope = new ReportingScopeResolver($request->user());
        $scope->requirePermission($permission);
        return $scope;
    }
}
