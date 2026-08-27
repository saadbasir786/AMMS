<?php

namespace App\Http\Controllers\Api;

use App\Actions\MeetingRequests\AcceptMeetingScheduleProposal;
use App\Actions\MeetingRequests\ApproveRequesterSideRequest;
use App\Actions\MeetingRequests\ApproveTargetSideRequest;
use App\Actions\MeetingRequests\ConfirmInterDirectorateSchedule;
use App\Actions\MeetingRequests\ConfirmRequesterParticipants;
use App\Actions\MeetingRequests\ConfirmTargetParticipants;
use App\Actions\MeetingRequests\ConvertInterDirectorateRequestToMeeting;
use App\Actions\MeetingRequests\CreateInterDirectorateMeetingRequest;
use App\Actions\MeetingRequests\NominateMeetingParticipant;
use App\Actions\MeetingRequests\ProposeMeetingSchedule;
use App\Actions\MeetingRequests\RejectRequesterSideRequest;
use App\Actions\MeetingRequests\RejectTargetSideRequest;
use App\Actions\MeetingRequests\ResubmitRequesterSideRequest;
use App\Actions\MeetingRequests\ResubmitToTarget;
use App\Actions\MeetingRequests\ReturnRequesterSideRequest;
use App\Actions\MeetingRequests\ReturnTargetSideRequest;
use App\Actions\MeetingRequests\StartInterDirectorateScheduling;
use App\Actions\MeetingRequests\StartTargetReview;
use App\Actions\MeetingRequests\SubmitInterDirectorateRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\MeetingRequests\FinalizeInterDirectorateMeetingRequest;
use App\Http\Requests\MeetingRequests\InterDirectorateParticipantRequest;
use App\Http\Requests\MeetingRequests\InterDirectorateReasonRequest;
use App\Http\Requests\MeetingRequests\InterDirectorateScheduleProposalRequest;
use App\Http\Requests\MeetingRequests\StoreInterDirectorateMeetingRequest;
use App\Http\Resources\MeetingRequestResource;
use App\Http\Resources\MeetingResource;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use Illuminate\Http\Request;

class InterDirectorateMeetingRequestController extends Controller
{
    public function store(StoreInterDirectorateMeetingRequest $request, CreateInterDirectorateMeetingRequest $action)
    {
        $this->authorize('create', MeetingRequest::class);
        return (new MeetingRequestResource($action($request->user(), $request->validated())))->additional(['success' => true, 'message' => 'درخواست بین‌ریاستی ایجاد شد.'])->response()->setStatusCode(201);
    }

    public function incoming(Request $request)
    {
        abort_unless($request->user()?->isActive() && $request->user()->can('meeting-request.view-incoming'), 403, 'FORBIDDEN');
        $unitIds = array_unique(array_merge($request->user()->scopedRoleUnitIds('EXECUTIVE_MANAGEMENT'), $request->user()->scopedRoleUnitIds('DIRECTOR')));
        $query = MeetingRequest::with(['units.unit', 'requestingUnit', 'creator.employee', 'actions.actor'])->where('flow_type', 'INTER_DIRECTORATE')->whereHas('units', fn ($q) => $q->where('role', 'TARGET')->whereIn('organizational_unit_id', $unitIds ?: [-1]));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('requesting_unit_id')) $query->whereHas('units', fn ($q) => $q->where('role', 'REQUESTER')->where('organizational_unit_id', $request->integer('requesting_unit_id')));
        if ($request->filled('priority')) $query->where('priority', $request->string('priority'));
        return MeetingRequestResource::collection($query->latest()->paginate(15))->additional(['success' => true, 'message' => 'درخواست‌های وارده.']);
    }

    public function submit(Request $request, MeetingRequest $meetingRequest, SubmitInterDirectorateRequest $action)
    {
        $this->authorize('flow02RequesterEdit', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست برای تأیید ریاست درخواست‌کننده ارسال شد.');
    }

    public function requesterApprove(Request $request, MeetingRequest $meetingRequest, ApproveRequesterSideRequest $action)
    {
        $this->authorize('flow02RequesterApprove', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست تأیید و به ریاست مقصد ارسال شد.');
    }

    public function requesterReturn(InterDirectorateReasonRequest $request, MeetingRequest $meetingRequest, ReturnRequesterSideRequest $action)
    {
        $this->authorize('flow02RequesterApprove', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['reason']), 'درخواست جهت اصلاح برگشت داده شد.');
    }

    public function requesterReject(InterDirectorateReasonRequest $request, MeetingRequest $meetingRequest, RejectRequesterSideRequest $action)
    {
        $this->authorize('flow02RequesterApprove', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['reason']), 'درخواست داخلی رد شد.');
    }

    public function resubmitRequester(Request $request, MeetingRequest $meetingRequest, ResubmitRequesterSideRequest $action)
    {
        $this->authorize('flow02RequesterEdit', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست اصلاح‌شده دوباره برای تأیید ریاست درخواست‌کننده ارسال شد.');
    }

    public function targetReview(Request $request, MeetingRequest $meetingRequest, StartTargetReview $action)
    {
        $this->authorize('flow02TargetReview', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'بررسی ریاست مقصد آغاز شد.');
    }

    public function targetApprove(Request $request, MeetingRequest $meetingRequest, ApproveTargetSideRequest $action)
    {
        $this->authorize('flow02TargetApprove', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست توسط ریاست مقصد تأیید شد.');
    }

    public function targetReturn(InterDirectorateReasonRequest $request, MeetingRequest $meetingRequest, ReturnTargetSideRequest $action)
    {
        $this->authorize('flow02TargetReturn', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['reason']), 'درخواست جهت وضاحت برگشت داده شد.');
    }

    public function targetReject(InterDirectorateReasonRequest $request, MeetingRequest $meetingRequest, RejectTargetSideRequest $action)
    {
        $this->authorize('flow02TargetReject', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['reason']), 'درخواست توسط ریاست مقصد رد شد.');
    }

    public function resubmitTarget(Request $request, MeetingRequest $meetingRequest, ResubmitToTarget $action)
    {
        $this->authorize('flow02RequesterEdit', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست اصلاح‌شده دوباره به ریاست مقصد ارسال شد.');
    }

    public function startScheduling(Request $request, MeetingRequest $meetingRequest, StartInterDirectorateScheduling $action)
    {
        $this->authorize('flow02LeadCoordinate', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'مذاکرهٔ زمان‌بندی آغاز شد.');
    }

    public function proposeSchedule(InterDirectorateScheduleProposalRequest $request, MeetingRequest $meetingRequest, ProposeMeetingSchedule $action)
    {
        $this->authorize('flow02Coordinate', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()), 'پیشنهاد زمان‌بندی ثبت شد.');
    }

    public function acceptSchedule(Request $request, MeetingRequest $meetingRequest, MeetingScheduleProposal $proposal, AcceptMeetingScheduleProposal $action)
    {
        $this->authorize('flow02Coordinate', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $proposal), 'پیشنهاد زمان‌بندی پذیرفته شد.');
    }

    public function nominate(InterDirectorateParticipantRequest $request, MeetingRequest $meetingRequest, NominateMeetingParticipant $action)
    {
        $this->authorize('flow02Coordinate', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()), 'اشتراک‌کننده ثبت شد.');
    }

    public function confirmRequesterParticipants(Request $request, MeetingRequest $meetingRequest, ConfirmRequesterParticipants $action)
    {
        $this->authorize('flow02Coordinate', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'اشتراک‌کنندگان ریاست درخواست‌کننده تأیید شدند.');
    }

    public function confirmTargetParticipants(Request $request, MeetingRequest $meetingRequest, ConfirmTargetParticipants $action)
    {
        $this->authorize('flow02Coordinate', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'اشتراک‌کنندگان ریاست مقصد تأیید شدند.');
    }

    public function confirmSchedule(Request $request, MeetingRequest $meetingRequest, ConfirmInterDirectorateSchedule $action)
    {
        $this->authorize('flow02LeadCoordinate', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'زمان‌بندی نهایی تأیید شد.');
    }

    public function finalize(FinalizeInterDirectorateMeetingRequest $request, MeetingRequest $meetingRequest, ConvertInterDirectorateRequestToMeeting $action)
    {
        $this->authorize('flow02LeadCoordinate', $meetingRequest);
        return (new MeetingResource($action($request->user(), $meetingRequest, $request->validated())))->additional(['success' => true, 'message' => 'جلسهٔ بین‌ریاستی ایجاد شد.']);
    }

    private function ok(MeetingRequest $request, string $message)
    {
        return (new MeetingRequestResource($request))->additional(['success' => true, 'message' => $message]);
    }
}
