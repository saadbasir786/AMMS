<?php

namespace App\Http\Controllers\Api\MeetingRequests;

use App\Actions\MeetingRequests\AcknowledgeMultiHighAuthoritySchedule;
use App\Actions\MeetingRequests\ApproveMultiHighByAuthority;
use App\Actions\MeetingRequests\ApproveMultiHighParticipatingUnit;
use App\Actions\MeetingRequests\ApproveMultiHighRequesterSide;
use App\Actions\MeetingRequests\ConfirmMultiHighAuthorityParticipants;
use App\Actions\MeetingRequests\ConfirmMultiHighAuthoritySchedule;
use App\Actions\MeetingRequests\ConfirmMultiHighUnitParticipants;
use App\Actions\MeetingRequests\CreateMultiHighAuthorityMeetingRequest;
use App\Actions\MeetingRequests\ConvertMultiHighAuthorityRequestToMeeting;
use App\Actions\MeetingRequests\ForwardMultiHighToHighAuthority;
use App\Actions\MeetingRequests\ManageMultiHighAuthorityParticipants;
use App\Actions\MeetingRequests\ManageMultiHighUnitParticipants;
use App\Actions\MeetingRequests\ProposeMultiHighAuthoritySchedule;
use App\Actions\MeetingRequests\RejectMultiHighByAuthority;
use App\Actions\MeetingRequests\RejectMultiHighParticipatingUnit;
use App\Actions\MeetingRequests\RejectMultiHighRequesterSide;
use App\Actions\MeetingRequests\RequestMultiHighAlternativeSchedule;
use App\Actions\MeetingRequests\ResubmitMultiHighRequest;
use App\Actions\MeetingRequests\ReturnMultiHighByAuthority;
use App\Actions\MeetingRequests\ReturnMultiHighFromAuthorityOffice;
use App\Actions\MeetingRequests\ReturnMultiHighParticipatingUnit;
use App\Actions\MeetingRequests\ReturnMultiHighRequesterSide;
use App\Actions\MeetingRequests\StartMultiHighAuthorityOfficeReview;
use App\Actions\MeetingRequests\StartMultiHighAuthorityScheduling;
use App\Actions\MeetingRequests\StartMultiHighUnitReview;
use App\Actions\MeetingRequests\SubmitMultiHighAuthorityRequest;
use App\Actions\MeetingRequests\SubmitMultiHighRequestToAuthorityOffice;
use App\Actions\MeetingRequests\UpdateMultiHighAuthorityMeetingRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeetingRequestResource;
use App\Http\Resources\MeetingResource;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\OrganizationalUnit;
use Illuminate\Http\Request;

class MultiHighAuthorityMeetingRequestController extends Controller
{
    public function store(Request $request, CreateMultiHighAuthorityMeetingRequest $action)
    {
        $this->authorize('create', MeetingRequest::class);
        $data = $request->validate(['requesting_unit_id' => ['required', 'integer'], 'participating_unit_ids' => ['required', 'array', 'min:1'], 'participating_unit_ids.*' => ['integer'], 'subject' => ['required', 'string', 'max:255'], 'purpose' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'priority' => ['required', 'string'], 'meeting_justification' => ['nullable', 'string'], 'requested_outcome' => ['nullable', 'string'], 'preferred_date' => ['nullable', 'date'], 'preferred_start_time' => ['nullable'], 'estimated_duration_minutes' => ['nullable', 'integer'], 'proposed_location' => ['nullable', 'string'], 'participant_ids' => ['nullable', 'array'], 'participant_ids.*' => ['integer']]);
        return (new MeetingRequestResource($action($request->user(), $data)))->additional(['success' => true, 'message' => 'درخواست چندریاستی با مقام عالی ایجاد شد.'])->response()->setStatusCode(201);
    }

    public function update(Request $request, MeetingRequest $meetingRequest, UpdateMultiHighAuthorityMeetingRequest $action) { $this->authorize('flow05RequesterEdit', $meetingRequest); $data = $request->validate(['requesting_unit_id' => ['sometimes', 'integer'], 'participating_unit_ids' => ['sometimes', 'array', 'min:1'], 'participating_unit_ids.*' => ['integer'], 'subject' => ['sometimes', 'string', 'max:255'], 'purpose' => ['sometimes', 'string'], 'description' => ['sometimes', 'nullable', 'string'], 'meeting_justification' => ['sometimes', 'nullable', 'string'], 'requested_outcome' => ['sometimes', 'nullable', 'string'], 'preferred_date' => ['sometimes', 'nullable', 'date'], 'preferred_start_time' => ['sometimes', 'nullable'], 'proposed_location' => ['sometimes', 'nullable', 'string']]); return $this->ok($action($request->user(), $meetingRequest, $data), 'درخواست FLOW-05 اصلاح شد.'); }

    public function submit(Request $request, MeetingRequest $meetingRequest, SubmitMultiHighAuthorityRequest $action) { $this->authorize('flow05RequesterEdit', $meetingRequest); return $this->ok($action($request->user(), $meetingRequest), 'درخواست برای تأیید داخلی ارسال شد.'); }
    public function requesterApprove(Request $request, MeetingRequest $meetingRequest, ApproveMultiHighRequesterSide $action) { $this->authorize('flow05RequesterApprove', $meetingRequest); return $this->ok($action($request->user(), $meetingRequest), 'تأیید ریاست درخواست‌کننده ثبت شد.'); }
    public function requesterReturn(Request $request, MeetingRequest $meetingRequest, ReturnMultiHighRequesterSide $action) { $this->authorize('flow05RequesterApprove', $meetingRequest); $data = $request->validate(['reason' => ['required', 'string']]); return $this->ok($action($request->user(), $meetingRequest, $data['reason']), 'درخواست برای اصلاح برگشت داده شد.'); }
    public function requesterReject(Request $request, MeetingRequest $meetingRequest, RejectMultiHighRequesterSide $action) { $this->authorize('flow05RequesterApprove', $meetingRequest); $data = $request->validate(['reason' => ['required', 'string']]); return $this->ok($action($request->user(), $meetingRequest, $data['reason']), 'درخواست داخلی رد شد.'); }
    public function resubmit(Request $request, MeetingRequest $meetingRequest, ResubmitMultiHighRequest $action) { $this->authorize('flow05RequesterEdit', $meetingRequest); return $this->ok($action($request->user(), $meetingRequest), 'درخواست دوباره ارسال شد.'); }

    public function unitReview(Request $request, MeetingRequest $meetingRequest, OrganizationalUnit $unit, StartMultiHighUnitReview $action) { $this->authorize('flow05UnitReview', [$meetingRequest, $unit->id]); return $this->ok($action($request->user(), $meetingRequest, $unit->id), 'بررسی ریاست اشتراک‌کننده آغاز شد.'); }
    public function unitApprove(Request $request, MeetingRequest $meetingRequest, OrganizationalUnit $unit, ApproveMultiHighParticipatingUnit $action) { $this->authorize('flow05UnitApprove', [$meetingRequest, $unit->id]); return $this->ok($action($request->user(), $meetingRequest, $unit->id), 'تأیید ریاست اشتراک‌کننده ثبت شد.'); }
    public function unitReturn(Request $request, MeetingRequest $meetingRequest, OrganizationalUnit $unit, ReturnMultiHighParticipatingUnit $action) { $this->authorize('flow05UnitApprove', [$meetingRequest, $unit->id]); $data = $request->validate(['reason' => ['required', 'string']]); return $this->ok($action($request->user(), $meetingRequest, $unit->id, $data['reason']), 'درخواست برای وضاحت برگشت داده شد.'); }
    public function unitReject(Request $request, MeetingRequest $meetingRequest, OrganizationalUnit $unit, RejectMultiHighParticipatingUnit $action) { $this->authorize('flow05UnitApprove', [$meetingRequest, $unit->id]); $data = $request->validate(['reason' => ['required', 'string']]); return $this->ok($action($request->user(), $meetingRequest, $unit->id, $data['reason']), 'درخواست توسط ریاست اشتراک‌کننده رد شد.'); }

    public function authorityOfficeSubmit(Request $request, MeetingRequest $meetingRequest, SubmitMultiHighRequestToAuthorityOffice $action) { $this->authorize('flow05Coordinate', $meetingRequest); return $this->ok($action($request->user(), $meetingRequest), 'درخواست به دفتر مقام ارسال شد.'); }
    public function authorityOfficeReview(Request $request, MeetingRequest $meetingRequest, StartMultiHighAuthorityOfficeReview $action) { $this->authorize('flow05AuthorityOfficeReview', $meetingRequest); return $this->ok($action($request->user(), $meetingRequest), 'بررسی دفتر مقام آغاز شد.'); }
    public function authorityOfficeReturn(Request $request, MeetingRequest $meetingRequest, ReturnMultiHighFromAuthorityOffice $action) { $this->authorize('flow05AuthorityOfficeReview', $meetingRequest); $data = $request->validate(['reason' => ['required', 'string']]); return $this->ok($action($request->user(), $meetingRequest, $data['reason']), 'درخواست از دفتر مقام برگشت داده شد.'); }
    public function authorityOfficeResubmit(Request $request, MeetingRequest $meetingRequest, ResubmitMultiHighRequest $action) { $this->authorize('flow05RequesterEdit', $meetingRequest); return $this->ok($action($request->user(), $meetingRequest), 'درخواست اصلاح‌شده به دفتر مقام ارسال شد.'); }
    public function authorityOfficeForward(Request $request, MeetingRequest $meetingRequest, ForwardMultiHighToHighAuthority $action) { $this->authorize('flow05AuthorityOfficeReview', $meetingRequest); return $this->ok($action($request->user(), $meetingRequest), 'درخواست به مقام عالی ارسال شد.'); }

    public function authorityApprove(Request $request, MeetingRequest $meetingRequest, ApproveMultiHighByAuthority $action) { $this->authorize('flow05AuthorityDecision', $meetingRequest); $data = $request->validate(['comment' => ['nullable', 'string']]); return $this->ok($action($request->user(), $meetingRequest, $data['comment'] ?? ''), 'تأیید مقام عالی ثبت شد.'); }
    public function authorityReturn(Request $request, MeetingRequest $meetingRequest, ReturnMultiHighByAuthority $action) { $this->authorize('flow05AuthorityDecision', $meetingRequest); $data = $request->validate(['reason' => ['required', 'string']]); return $this->ok($action($request->user(), $meetingRequest, $data['reason']), 'درخواست از سوی مقام عالی برگشت داده شد.'); }
    public function authorityReject(Request $request, MeetingRequest $meetingRequest, RejectMultiHighByAuthority $action) { $this->authorize('flow05AuthorityDecision', $meetingRequest); $data = $request->validate(['reason' => ['required', 'string']]); return $this->ok($action($request->user(), $meetingRequest, $data['reason']), 'درخواست توسط مقام عالی رد شد.'); }

    public function schedulingStart(Request $request, MeetingRequest $meetingRequest, StartMultiHighAuthorityScheduling $action) { $this->authorize('flow05Schedule', $meetingRequest); return $this->ok($action($request->user(), $meetingRequest), 'زمان‌بندی رسمی آغاز شد.'); }
    public function schedulePropose(Request $request, MeetingRequest $meetingRequest, ProposeMultiHighAuthoritySchedule $action) { $this->authorize('flow05Schedule', $meetingRequest); $data = $request->validate(['meeting_date' => ['required', 'date'], 'start_time' => ['required'], 'end_time' => ['required'], 'location' => ['required', 'string'], 'comment' => ['nullable', 'string']]); return $this->ok($action($request->user(), $meetingRequest, $data), 'پیشنهاد رسمی زمان‌بندی ثبت شد.'); }
    public function scheduleAcknowledge(Request $request, MeetingRequest $meetingRequest, AcknowledgeMultiHighAuthoritySchedule $action) { $this->authorize('flow05ScheduleAcknowledge', $meetingRequest); $data = $request->validate(['proposal_id' => ['required', 'integer'], 'organizational_unit_id' => ['required', 'integer']]); $proposal = MeetingScheduleProposal::findOrFail($data['proposal_id']); return $this->ok($action($request->user(), $meetingRequest, $proposal, (int) $data['organizational_unit_id']), 'پذیرش زمان‌بندی ریاست ثبت شد.'); }
    public function scheduleAlternative(Request $request, MeetingRequest $meetingRequest, RequestMultiHighAlternativeSchedule $action) { $this->authorize('flow05ScheduleAcknowledge', $meetingRequest); $data = $request->validate(['organizational_unit_id' => ['required', 'integer'], 'reason' => ['required', 'string']]); return $this->ok($action($request->user(), $meetingRequest, $data), 'درخواست زمان بدیل ثبت شد.'); }
    public function scheduleConfirm(Request $request, MeetingRequest $meetingRequest, ConfirmMultiHighAuthoritySchedule $action) { $this->authorize('flow05Schedule', $meetingRequest); return $this->ok($action($request->user(), $meetingRequest), 'زمان‌بندی رسمی نهایی شد.'); }

    public function unitParticipants(Request $request, MeetingRequest $meetingRequest, OrganizationalUnit $unit, ManageMultiHighUnitParticipants $action) { $this->authorize('flow05UnitParticipantsManage', [$meetingRequest, $unit->id]); $data = $request->validate(['employee_ids' => ['required', 'array'], 'employee_ids.*' => ['integer']]); return $this->ok($action($request->user(), $meetingRequest, $unit->id, $data['employee_ids']), 'فهرست اشتراک‌کنندگان ریاست ذخیره شد.'); }
    public function unitParticipantsConfirm(Request $request, MeetingRequest $meetingRequest, OrganizationalUnit $unit, ConfirmMultiHighUnitParticipants $action) { $this->authorize('flow05UnitParticipantsConfirm', [$meetingRequest, $unit->id]); return $this->ok($action($request->user(), $meetingRequest, $unit->id), 'فهرست اشتراک‌کنندگان ریاست تأیید شد.'); }
    public function authorityParticipants(Request $request, MeetingRequest $meetingRequest, ManageMultiHighAuthorityParticipants $action) { $this->authorize('flow05AuthorityParticipants', $meetingRequest); $data = $request->validate(['employee_ids' => ['required', 'array'], 'employee_ids.*' => ['integer']]); return $this->ok($action($request->user(), $meetingRequest, $data['employee_ids']), 'فهرست اشتراک‌کنندگان مقام ذخیره شد.'); }
    public function authorityParticipantsConfirm(Request $request, MeetingRequest $meetingRequest, ConfirmMultiHighAuthorityParticipants $action) { $this->authorize('flow05AuthorityParticipants', $meetingRequest); return $this->ok($action($request->user(), $meetingRequest), 'فهرست اشتراک‌کنندگان مقام تأیید شد.'); }
    public function finalize(Request $request, MeetingRequest $meetingRequest, ConvertMultiHighAuthorityRequestToMeeting $action) { $this->authorize('flow05Finalize', $meetingRequest); $data = $request->validate(['agenda' => ['nullable', 'string']]); return (new MeetingResource($action($request->user(), $meetingRequest, $data)))->additional(['success' => true, 'message' => 'جلسهٔ چندریاستی با مقام عالی ایجاد شد.']); }

    private function ok(MeetingRequest $request, string $message) { return (new MeetingRequestResource($request))->additional(['success' => true, 'message' => $message]); }
}
