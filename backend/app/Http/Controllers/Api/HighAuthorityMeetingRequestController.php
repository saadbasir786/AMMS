<?php

namespace App\Http\Controllers\Api;

use App\Actions\MeetingRequests\AcknowledgeAuthoritySchedule;
use App\Actions\MeetingRequests\ApproveHighAuthorityRequest;
use App\Actions\MeetingRequests\ApproveHighAuthorityRequesterSide;
use App\Actions\MeetingRequests\ConfirmHighAuthorityParticipants;
use App\Actions\MeetingRequests\ConfirmAuthoritySchedule;
use App\Actions\MeetingRequests\ConfirmHighAuthorityRequesterParticipants;
use App\Actions\MeetingRequests\ConvertHighAuthorityRequestToMeeting;
use App\Actions\MeetingRequests\CreateHighAuthorityMeetingRequest;
use App\Actions\MeetingRequests\ForwardRequestToHighAuthority;
use App\Actions\MeetingRequests\ProposeAuthoritySchedule;
use App\Actions\MeetingRequests\RejectByHighAuthority;
use App\Actions\MeetingRequests\RejectHighAuthorityRequesterSide;
use App\Actions\MeetingRequests\RequestAuthorityScheduleAlternative;
use App\Actions\MeetingRequests\ResubmitHighAuthorityMeetingRequest;
use App\Actions\MeetingRequests\ReturnByHighAuthority;
use App\Actions\MeetingRequests\ReturnFromAuthorityOffice;
use App\Actions\MeetingRequests\ReturnHighAuthorityRequesterSide;
use App\Actions\MeetingRequests\StartAuthorityOfficeReview;
use App\Actions\MeetingRequests\StartAuthorityScheduling;
use App\Actions\MeetingRequests\UpdateHighAuthorityParticipants;
use App\Actions\MeetingRequests\UpdateHighAuthorityRequesterParticipants;
use App\Actions\MeetingRequests\UpdateHighAuthorityMeetingRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\MeetingRequests\AuthorityScheduleAlternativeRequest;
use App\Http\Requests\MeetingRequests\AuthorityScheduleProposalRequest;
use App\Http\Requests\MeetingRequests\FinalizeHighAuthorityMeetingRequest;
use App\Http\Requests\MeetingRequests\HighAuthorityParticipantsRequest;
use App\Http\Requests\MeetingRequests\HighAuthorityReasonRequest;
use App\Http\Requests\MeetingRequests\StoreHighAuthorityMeetingRequest;
use App\Http\Requests\MeetingRequests\UpdateHighAuthorityMeetingRequest as UpdateHighAuthorityMeetingRequestForm;
use App\Http\Resources\MeetingRequestResource;
use App\Http\Resources\MeetingResource;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use Illuminate\Http\Request;

class HighAuthorityMeetingRequestController extends Controller
{
    public function store(StoreHighAuthorityMeetingRequest $request, CreateHighAuthorityMeetingRequest $action)
    {
        $this->authorize('create', MeetingRequest::class);
        return (new MeetingRequestResource($action($request->user(), $request->validated())))->additional(['success' => true, 'message' => 'درخواست جلسه با مقام عالی ایجاد شد.'])->response()->setStatusCode(201);
    }

    public function update(UpdateHighAuthorityMeetingRequestForm $request, MeetingRequest $meetingRequest, UpdateHighAuthorityMeetingRequest $action)
    {
        $this->authorize('flow04RequesterEdit', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()), 'درخواست مقام عالی اصلاح شد.');
    }

    public function submit(Request $request, MeetingRequest $meetingRequest, \App\Actions\MeetingRequests\SubmitHighAuthorityMeetingRequest $action)
    {
        $this->authorize('flow04RequesterEdit', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست برای تأیید داخلی ارسال شد.');
    }

    public function requesterApprove(Request $request, MeetingRequest $meetingRequest, ApproveHighAuthorityRequesterSide $action)
    {
        $this->authorize('flow04RequesterApprove', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست تأیید و به دفتر مقام ارسال شد.');
    }

    public function requesterReturn(HighAuthorityReasonRequest $request, MeetingRequest $meetingRequest, ReturnHighAuthorityRequesterSide $action)
    {
        $this->authorize('flow04RequesterApprove', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['reason']), 'درخواست جهت اصلاح برگشت داده شد.');
    }

    public function requesterReject(HighAuthorityReasonRequest $request, MeetingRequest $meetingRequest, RejectHighAuthorityRequesterSide $action)
    {
        $this->authorize('flow04RequesterApprove', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['reason']), 'درخواست داخلی رد شد.');
    }

    public function resubmit(Request $request, MeetingRequest $meetingRequest, ResubmitHighAuthorityMeetingRequest $action)
    {
        $this->authorize('flow04RequesterEdit', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست اصلاح‌شده دوباره ارسال شد.');
    }

    public function officeReview(Request $request, MeetingRequest $meetingRequest, StartAuthorityOfficeReview $action)
    {
        $this->authorize('flow04OfficeReview', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'بررسی دفتر مقام آغاز شد.');
    }

    public function officeReturn(HighAuthorityReasonRequest $request, MeetingRequest $meetingRequest, ReturnFromAuthorityOffice $action)
    {
        $this->authorize('flow04OfficeReview', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['reason']), 'درخواست از دفتر مقام جهت اصلاح برگشت داده شد.');
    }

    public function officeForward(Request $request, MeetingRequest $meetingRequest, ForwardRequestToHighAuthority $action)
    {
        $this->authorize('flow04OfficeForward', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست به مقام عالی ارسال شد.');
    }

    public function authorityApprove(Request $request, MeetingRequest $meetingRequest, ApproveHighAuthorityRequest $action)
    {
        $this->authorize('flow04AuthorityDecision', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->input('comment')), 'تصمیم تأیید مقام عالی ثبت شد.');
    }

    public function authorityReturn(HighAuthorityReasonRequest $request, MeetingRequest $meetingRequest, ReturnByHighAuthority $action)
    {
        $this->authorize('flow04AuthorityDecision', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['reason']), 'درخواست توسط مقام عالی جهت اصلاح برگشت داده شد.');
    }

    public function authorityReject(HighAuthorityReasonRequest $request, MeetingRequest $meetingRequest, RejectByHighAuthority $action)
    {
        $this->authorize('flow04AuthorityDecision', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['reason']), 'درخواست توسط مقام عالی رد شد.');
    }

    public function startScheduling(Request $request, MeetingRequest $meetingRequest, StartAuthorityScheduling $action)
    {
        $this->authorize('flow04Scheduler', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'زمان‌بندی دفتر مقام آغاز شد.');
    }

    public function proposeSchedule(AuthorityScheduleProposalRequest $request, MeetingRequest $meetingRequest, ProposeAuthoritySchedule $action)
    {
        $this->authorize('flow04Scheduler', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()), 'پیشنهاد رسمی زمان‌بندی ثبت شد.');
    }

    public function acknowledgeSchedule(Request $request, MeetingRequest $meetingRequest, MeetingScheduleProposal $proposal, AcknowledgeAuthoritySchedule $action)
    {
        $this->authorize('flow04RequesterScheduleAcknowledge', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $proposal), 'پیشنهاد رسمی زمان‌بندی تأیید شد.');
    }

    public function requestAlternative(AuthorityScheduleAlternativeRequest $request, MeetingRequest $meetingRequest, RequestAuthorityScheduleAlternative $action)
    {
        $this->authorize('flow04RequesterScheduleAcknowledge', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()), 'درخواست زمان بدیل ثبت شد.');
    }

    public function confirmSchedule(Request $request, MeetingRequest $meetingRequest, MeetingScheduleProposal $proposal, ConfirmAuthoritySchedule $action)
    {
        $this->authorize('flow04Scheduler', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $proposal), 'زمان‌بندی رسمی مقام تأیید نهایی شد.');
    }

    public function updateRequesterParticipants(HighAuthorityParticipantsRequest $request, MeetingRequest $meetingRequest, UpdateHighAuthorityRequesterParticipants $action)
    {
        $this->authorize('flow04RequesterParticipants', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['employee_ids']), 'فهرست اشتراک‌کنندگان ریاست درخواست‌کننده ثبت شد.');
    }

    public function confirmRequesterParticipants(Request $request, MeetingRequest $meetingRequest, ConfirmHighAuthorityRequesterParticipants $action)
    {
        $this->authorize('flow04RequesterParticipants', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'فهرست اشتراک‌کنندگان ریاست درخواست‌کننده تأیید شد.');
    }

    public function updateAuthorityParticipants(HighAuthorityParticipantsRequest $request, MeetingRequest $meetingRequest, UpdateHighAuthorityParticipants $action)
    {
        $this->authorize('flow04AuthorityParticipants', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['employee_ids']), 'فهرست اشتراک‌کنندگان دفتر مقام ثبت شد.');
    }

    public function confirmAuthorityParticipants(Request $request, MeetingRequest $meetingRequest, ConfirmHighAuthorityParticipants $action)
    {
        $this->authorize('flow04AuthorityParticipants', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'فهرست اشتراک‌کنندگان دفتر مقام تأیید شد.');
    }

    public function finalize(FinalizeHighAuthorityMeetingRequest $request, MeetingRequest $meetingRequest, ConvertHighAuthorityRequestToMeeting $action)
    {
        $this->authorize('flow04Finalize', $meetingRequest);
        return (new MeetingResource($action($request->user(), $meetingRequest, $request->validated())))->additional(['success' => true, 'message' => 'جلسه با مقام عالی ایجاد شد.']);
    }

    public function officeInbox(Request $request)
    {
        abort_unless($request->user()?->isActive(), 403, 'FORBIDDEN');
        $officeIds = array_unique(array_merge($request->user()->scopedRoleUnitIds('AUTHORITY_OFFICE'), $request->user()->scopedRoleUnitIds('EXECUTIVE_SECRETARY')));
        abort_unless($request->user()->can('authority-request.view') && $officeIds, 403, 'UNAUTHORIZED_AUTHORITY_OFFICE_USER');
        $query = MeetingRequest::with(['requestingUnit', 'authorityContext.authorityOfficeUnit', 'creator.employee', 'actions.actor'])
            ->whereIn('flow_type', ['DIRECTORATE_WITH_HIGH_AUTHORITY', 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY'])
            ->whereHas('authorityContext', fn ($q) => $q->whereIn('authority_office_unit_id', $officeIds));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        return MeetingRequestResource::collection($query->latest()->paginate(15))->additional(['success' => true, 'message' => 'درخواست‌های مقام عالی در دفتر.']);
    }

    public function authorityInbox(Request $request)
    {
        abort_unless($request->user()?->isActive() && $request->user()->can('authority-request.view') && $request->user()->employee_id, 403, 'UNAUTHORIZED_HIGH_AUTHORITY_DECISION');
        $query = MeetingRequest::with(['requestingUnit', 'authorityContext.authorityEmployee', 'authorityContext.authorityPosition', 'creator.employee', 'actions.actor'])
            ->whereIn('flow_type', ['DIRECTORATE_WITH_HIGH_AUTHORITY', 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY'])
            ->where('status', 'PENDING_HIGH_AUTHORITY_DECISION')
            ->whereHas('authorityContext', fn ($q) => $q->where('authority_employee_id', $request->user()->employee_id));
        return MeetingRequestResource::collection($query->latest()->paginate(15))->additional(['success' => true, 'message' => 'درخواست‌های منتظر تصمیم مقام عالی.']);
    }

    private function ok(MeetingRequest $request, string $message)
    {
        return (new MeetingRequestResource($request))->additional(['success' => true, 'message' => $message]);
    }
}
