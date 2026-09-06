<?php

namespace App\Http\Controllers\Api\MeetingRequests;

use App\Actions\MeetingRequests\ApproveMeetingRequest;
use App\Actions\MeetingRequests\ConfirmMeetingSchedule;
use App\Actions\MeetingRequests\ConvertRequestToMeeting;
use App\Actions\MeetingRequests\RejectMeetingRequest;
use App\Actions\MeetingRequests\ResubmitMeetingRequest;
use App\Actions\MeetingRequests\ReturnMeetingRequest;
use App\Actions\MeetingRequests\StartScheduling;
use App\Actions\MeetingRequests\SubmitMeetingRequest;
use App\Actions\MeetingRequests\UpdateInterDirectorateMeetingRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\MeetingRequests\FinalizeMeetingRequest;
use App\Http\Requests\MeetingRequests\ReviewMeetingRequestRequest;
use App\Http\Requests\MeetingRequests\ScheduleMeetingRequest;
use App\Http\Requests\MeetingRequests\StoreMeetingRequestRequest;
use App\Http\Requests\MeetingRequests\UpdateMeetingRequestRequest;
use App\Http\Resources\MeetingRequestResource;
use App\Models\MeetingRequest;
use App\Services\MeetingRequestService;
use App\Support\MeetingRequestActionCapabilities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeetingRequestController extends Controller
{
    private const INTERNAL_DIRECTORATE_FLOW_TYPE = 'INTERNAL_DIRECTORATE';
    private const INTER_DIRECTORATE_FLOW_TYPE = 'INTER_DIRECTORATE';

    public function __construct(private MeetingRequestService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', MeetingRequest::class);
        $user = $request->user();
        $query = MeetingRequest::with(['requestingUnit','units.unit','creator.employee','participants.employee','nominations.employee.unit','scheduleProposals.proposedByUnit','meeting'])->latest();
        $unitIds = array_unique(array_merge($user->scopedRoleUnitIds('EXECUTIVE_MANAGEMENT'), $user->scopedRoleUnitIds('DIRECTOR')));
        $officeIds = array_unique(array_merge($user->scopedRoleUnitIds('AUTHORITY_OFFICE'), $user->scopedRoleUnitIds('EXECUTIVE_SECRETARY')));
        $query->where(function ($q) use ($user, $unitIds, $officeIds): void {
            $q->whereIn('requesting_unit_id', $unitIds ?: [-1]);
            if ($officeIds) $q->orWhereHas('authorityContext', fn ($context) => $context->whereIn('authority_office_unit_id', $officeIds));
            if ($user->employee_id) $q->orWhereHas('authorityContext', fn ($context) => $context->where('authority_employee_id', $user->employee_id));
            if ($unitIds) $q->orWhereHas('units', fn ($units) => $units->whereIn('role', ['REQUESTER','TARGET','PARTICIPATING'])->whereIn('organizational_unit_id', $unitIds));
        });
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('priority')) $query->where('priority', $request->string('priority'));
        if ($request->filled('search')) $query->where(fn($q) => $q->where('subject','like','%'.$request->string('search').'%')->orWhere('request_number','like','%'.$request->string('search').'%'));
        return MeetingRequestResource::collection($query->paginate(15))->additional(['success'=>true,'message'=>'فهرست درخواست‌های جلسه.']);
    }

    public function store(StoreMeetingRequestRequest $request)
    {
        $this->authorize('create', MeetingRequest::class);
        $resource = new MeetingRequestResource($this->service->create($request->user(), $request->validated()));
        return $resource->additional(['success'=>true,'message'=>'پیش‌نویس درخواست ایجاد شد.'])->response()->setStatusCode(201);
    }

    public function show(MeetingRequest $meetingRequest)
    {
        $this->authorize('view', $meetingRequest);
        return (new MeetingRequestResource($meetingRequest->load(['requestingUnit','units.unit','creator.employee','coordinator','approver','participants.employee.unit','nominations.employee.unit','unitReviews.unit','participantConfirmations.unit','scheduleProposals.proposedByUnit','scheduleProposals.acceptances.unit','attachments','actions.actor','meeting.units.unit','meeting.participants.employee','meeting.chairperson','authorityContext.authorityOfficeUnit','authorityContext.authorityPosition','authorityContext.authorityEmployee','authorityContext.currentDirectiveVersion','authorityContext.organizationAuthority','authorityScheduleAcknowledgements','authorityScheduleUnitAcknowledgements.unit','authorityParticipantConfirmations','directiveVersions.preparedBy','directiveVersions.issuer','currentDirectiveVersion','unitResponses.unit','unitResponses.directiveVersion','unitResponses.coordinationStarter','unitResponses.acknowledger','directiveClarifications.unit','directiveClarifications.requester','directiveClarifications.responder'])))->additional(['success'=>true,'message'=>'جزئیات درخواست.']);
    }

    public function actionCapabilities(Request $request, MeetingRequest $meetingRequest, MeetingRequestActionCapabilities $capabilities)
    {
        $this->authorize('view', $meetingRequest);

        return response()->json([
            'success' => true,
            'data' => $capabilities->resolve($meetingRequest, $request->user()),
        ]);
    }

    public function update(UpdateMeetingRequestRequest $request, MeetingRequest $meetingRequest, UpdateInterDirectorateMeetingRequest $interDirectorateUpdate)
    {
        if ($meetingRequest->flow_type === self::INTER_DIRECTORATE_FLOW_TYPE) {
            $this->authorize('interDirectorateRequesterEdit', $meetingRequest);
            return (new MeetingRequestResource($interDirectorateUpdate($request->user(), $meetingRequest, $request->validated())))->additional(['success'=>true,'message'=>'درخواست بین‌ریاستی اصلاح شد.']);
        }

        $this->authorize('update', $meetingRequest);
        if ($meetingRequest->flow_type === self::INTERNAL_DIRECTORATE_FLOW_TYPE) $this->assertCurrentExecutiveManager($request, $meetingRequest);
        return (new MeetingRequestResource($this->service->update($request->user(), $meetingRequest, $request->validated())))->additional(['success'=>true,'message'=>'درخواست اصلاح شد.']);
    }

    public function submit(Request $request, MeetingRequest $meetingRequest, SubmitMeetingRequest $action, ResubmitMeetingRequest $resubmitAction)
    {
        $this->assertInternalDirectorateRequest($meetingRequest);
        $this->authorize('submit', $meetingRequest);
        $this->assertCurrentExecutiveManager($request, $meetingRequest);
        $operation = $meetingRequest->status === 'RETURNED_FOR_REVISION' ? $resubmitAction : $action;
        return (new MeetingRequestResource($operation($request->user(), $meetingRequest)))->additional(['success'=>true,'message'=>'درخواست برای تأیید ارسال شد.']);
    }

    public function approve(Request $request, MeetingRequest $meetingRequest, ApproveMeetingRequest $action)
    {
        $this->assertInternalDirectorateRequest($meetingRequest);
        abort_unless(
            Gate::forUser($request->user())->allows('approveInternal', $meetingRequest),
            403,
            'UNAUTHORIZED_APPROVER',
        );
        return (new MeetingRequestResource($action($request->user(), $meetingRequest)))->additional(['success'=>true,'message'=>'درخواست تأیید شد.']);
    }

    public function returnForRevision(ReviewMeetingRequestRequest $request, MeetingRequest $meetingRequest, ReturnMeetingRequest $action)
    {
        $this->assertInternalDirectorateRequest($meetingRequest);
        $this->authorize('review', $meetingRequest);
        return (new MeetingRequestResource($action($request->user(), $meetingRequest, $request->validated()['reason'])))->additional(['success'=>true,'message'=>'درخواست جهت اصلاح برگشت داده شد.']);
    }

    public function reject(ReviewMeetingRequestRequest $request, MeetingRequest $meetingRequest, RejectMeetingRequest $action)
    {
        $this->assertInternalDirectorateRequest($meetingRequest);
        $this->authorize('review', $meetingRequest);
        return (new MeetingRequestResource($action($request->user(), $meetingRequest, $request->validated()['reason'])))->additional(['success'=>true,'message'=>'درخواست رد شد.']);
    }

    public function startScheduling(Request $request, MeetingRequest $meetingRequest, StartScheduling $action)
    {
        $this->assertInternalDirectorateRequest($meetingRequest);
        $this->authorize('schedule', $meetingRequest);
        $this->assertCurrentExecutiveManager($request, $meetingRequest);
        return (new MeetingRequestResource($action($request->user(), $meetingRequest)))->additional(['success'=>true,'message'=>'زمان‌بندی آغاز شد.']);
    }

    public function confirmSchedule(ScheduleMeetingRequest $request, MeetingRequest $meetingRequest, ConfirmMeetingSchedule $action)
    {
        $this->assertInternalDirectorateRequest($meetingRequest);
        $this->authorize('schedule', $meetingRequest);
        $this->assertCurrentExecutiveManager($request, $meetingRequest);
        return (new MeetingRequestResource($action($request->user(), $meetingRequest, $request->validated())))->additional(['success'=>true,'message'=>'زمان‌بندی نهایی ثبت شد.']);
    }

    public function finalize(FinalizeMeetingRequest $request, MeetingRequest $meetingRequest, ConvertRequestToMeeting $action)
    {
        $this->assertInternalDirectorateRequest($meetingRequest);
        $this->authorize('finalize', $meetingRequest);
        $this->assertCurrentExecutiveManager($request, $meetingRequest);
        return (new \App\Http\Resources\MeetingResource($action($request->user(), $meetingRequest, $request->validated())))->additional(['success'=>true,'message'=>'جلسه با موفقیت نهایی و ایجاد شد.']);
    }

    private function assertInternalDirectorateRequest(MeetingRequest $meetingRequest): void
    {
        abort_unless($meetingRequest->flow_type === self::INTERNAL_DIRECTORATE_FLOW_TYPE, 422, 'FLOW_ENDPOINT_MISMATCH');
    }

    private function assertCurrentExecutiveManager(Request $request, MeetingRequest $meetingRequest): void
    {
        abort_unless($request->user()->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $meetingRequest->requesting_unit_id), 403, 'FORBIDDEN');
    }
}
