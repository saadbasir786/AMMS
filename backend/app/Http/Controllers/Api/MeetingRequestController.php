<?php

namespace App\Http\Controllers\Api;

use App\Actions\MeetingRequests\ApproveMeetingRequest;
use App\Actions\MeetingRequests\ConfirmMeetingSchedule;
use App\Actions\MeetingRequests\ConvertRequestToMeeting;
use App\Actions\MeetingRequests\RejectMeetingRequest;
use App\Actions\MeetingRequests\ResubmitMeetingRequest;
use App\Actions\MeetingRequests\ReturnMeetingRequest;
use App\Actions\MeetingRequests\StartScheduling;
use App\Actions\MeetingRequests\SubmitMeetingRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\FinalizeMeetingRequest;
use App\Http\Requests\ReviewMeetingRequestRequest;
use App\Http\Requests\ScheduleMeetingRequest;
use App\Http\Requests\StoreMeetingRequestRequest;
use App\Http\Requests\UpdateMeetingRequestRequest;
use App\Http\Resources\MeetingRequestResource;
use App\Models\MeetingRequest;
use App\Services\MeetingRequestService;
use Illuminate\Http\Request;

class MeetingRequestController extends Controller
{
    public function __construct(private MeetingRequestService $service) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', MeetingRequest::class);
        $user = $request->user();
        $query = MeetingRequest::with(['requestingUnit','units.unit','creator.employee','participants.employee','nominations.employee.unit','scheduleProposals.proposedByUnit','meeting'])->latest();
        if (!$user->hasScopedRole('SYSTEM_ADMIN')) {
            $unitIds = array_unique(array_merge($user->scopedRoleUnitIds('EXECUTIVE_MANAGEMENT'), $user->scopedRoleUnitIds('DIRECTOR'), $user->scopedRoleUnitIds('MEETING_COORDINATOR')));
            $query->where(fn($q) => $q->where('created_by', $user->id)->orWhereIn('requesting_unit_id', $unitIds ?: [-1]));
        }
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
        return (new MeetingRequestResource($meetingRequest->load(['requestingUnit','units.unit','creator.employee','coordinator','approver','participants.employee.unit','nominations.employee.unit','unitReviews.unit','participantConfirmations.unit','scheduleProposals.proposedByUnit','scheduleProposals.acceptances.unit','attachments','actions.actor','meeting.units.unit','meeting.participants.employee','meeting.chairperson','authorityContext.authorityOfficeUnit','authorityContext.authorityPosition','authorityContext.authorityEmployee','authorityScheduleAcknowledgements','authorityScheduleUnitAcknowledgements.unit','authorityParticipantConfirmations'])))->additional(['success'=>true,'message'=>'جزئیات درخواست.']);
    }

    public function update(UpdateMeetingRequestRequest $request, MeetingRequest $meetingRequest)
    {
        $this->authorize('update', $meetingRequest);
        return (new MeetingRequestResource($this->service->update($request->user(), $meetingRequest, $request->validated())))->additional(['success'=>true,'message'=>'درخواست اصلاح شد.']);
    }

    public function submit(Request $request, MeetingRequest $meetingRequest, SubmitMeetingRequest $action, ResubmitMeetingRequest $resubmitAction)
    {
        $this->authorize('submit', $meetingRequest);
        $operation = $meetingRequest->status === 'RETURNED_FOR_REVISION' ? $resubmitAction : $action;
        return (new MeetingRequestResource($operation($request->user(), $meetingRequest)))->additional(['success'=>true,'message'=>'درخواست برای تأیید ارسال شد.']);
    }

    public function approve(Request $request, MeetingRequest $meetingRequest, ApproveMeetingRequest $action)
    {
        $this->authorize('review', $meetingRequest);
        return (new MeetingRequestResource($action($request->user(), $meetingRequest)))->additional(['success'=>true,'message'=>'درخواست تأیید شد.']);
    }

    public function returnForRevision(ReviewMeetingRequestRequest $request, MeetingRequest $meetingRequest, ReturnMeetingRequest $action)
    {
        $this->authorize('review', $meetingRequest);
        return (new MeetingRequestResource($action($request->user(), $meetingRequest, $request->validated()['reason'])))->additional(['success'=>true,'message'=>'درخواست جهت اصلاح برگشت داده شد.']);
    }

    public function reject(ReviewMeetingRequestRequest $request, MeetingRequest $meetingRequest, RejectMeetingRequest $action)
    {
        $this->authorize('review', $meetingRequest);
        return (new MeetingRequestResource($action($request->user(), $meetingRequest, $request->validated()['reason'])))->additional(['success'=>true,'message'=>'درخواست رد شد.']);
    }

    public function startScheduling(Request $request, MeetingRequest $meetingRequest, StartScheduling $action)
    {
        $this->authorize('schedule', $meetingRequest);
        return (new MeetingRequestResource($action($request->user(), $meetingRequest)))->additional(['success'=>true,'message'=>'زمان‌بندی آغاز شد.']);
    }

    public function confirmSchedule(ScheduleMeetingRequest $request, MeetingRequest $meetingRequest, ConfirmMeetingSchedule $action)
    {
        $this->authorize('schedule', $meetingRequest);
        return (new MeetingRequestResource($action($request->user(), $meetingRequest, $request->validated())))->additional(['success'=>true,'message'=>'زمان‌بندی نهایی ثبت شد.']);
    }

    public function finalize(FinalizeMeetingRequest $request, MeetingRequest $meetingRequest, ConvertRequestToMeeting $action)
    {
        $this->authorize('finalize', $meetingRequest);
        return (new \App\Http\Resources\MeetingResource($action($request->user(), $meetingRequest, $request->validated())))->additional(['success'=>true,'message'=>'جلسه با موفقیت نهایی و ایجاد شد.']);
    }
}
