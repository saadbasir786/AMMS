<?php

namespace App\Http\Controllers\Api\MeetingRequests;

use App\Actions\MeetingRequests\AcceptMultiScheduleProposal;
use App\Actions\MeetingRequests\ApproveMultiRequesterSide;
use App\Actions\MeetingRequests\ApproveMultiTargetUnit;
use App\Actions\MeetingRequests\ConfirmMultiDirectorateSchedule;
use App\Actions\MeetingRequests\ConfirmUnitParticipants;
use App\Actions\MeetingRequests\ConvertMultiDirectorateRequestToMeeting;
use App\Actions\MeetingRequests\CreateMultiDirectorateMeetingRequest;
use App\Actions\MeetingRequests\NominateMultiMeetingParticipant;
use App\Actions\MeetingRequests\ProposeMultiSchedule;
use App\Actions\MeetingRequests\RejectMultiRequesterSide;
use App\Actions\MeetingRequests\RejectMultiTargetUnit;
use App\Actions\MeetingRequests\ResubmitMultiDirectorateRequest;
use App\Actions\MeetingRequests\ReturnMultiRequesterSide;
use App\Actions\MeetingRequests\ReturnMultiTargetUnit;
use App\Actions\MeetingRequests\StartMultiDirectorateScheduling;
use App\Actions\MeetingRequests\StartMultiTargetReview;
use App\Actions\MeetingRequests\SubmitMultiDirectorateRequest;
use App\Actions\MeetingRequests\UpdateMultiDirectorateMeetingRequest as UpdateMultiDirectorateMeetingRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MeetingRequests\FinalizeMultiDirectorateMeetingRequest;
use App\Http\Requests\MeetingRequests\MultiDirectorateReasonRequest;
use App\Http\Requests\MeetingRequests\MultiParticipantRequest;
use App\Http\Requests\MeetingRequests\MultiScheduleAcceptanceRequest;
use App\Http\Requests\MeetingRequests\MultiScheduleProposalRequest;
use App\Http\Requests\MeetingRequests\StoreMultiDirectorateMeetingRequest;
use App\Http\Requests\MeetingRequests\UpdateMultiDirectorateMeetingRequest;
use App\Http\Resources\MeetingRequestResource;
use App\Http\Resources\MeetingResource;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\OrganizationalUnit;
use Illuminate\Http\Request;

class MultiDirectorateMeetingRequestController extends Controller
{
    public function store(StoreMultiDirectorateMeetingRequest $request, CreateMultiDirectorateMeetingRequest $action)
    {
        $this->authorize('create', MeetingRequest::class);
        return (new MeetingRequestResource($action($request->user(), $request->validated())))
            ->additional(['success' => true, 'message' => 'درخواست چندریاستی ایجاد شد.'])
            ->response()->setStatusCode(201);
    }

    public function incoming(Request $request)
    {
        abort_unless($request->user()?->isActive() && $request->user()->can('meeting-request.view-incoming'), 403, 'FORBIDDEN');
        $unitIds = array_unique(array_merge(
            $request->user()->scopedRoleUnitIds('EXECUTIVE_MANAGEMENT'),
            $request->user()->scopedRoleUnitIds('DIRECTOR'),
        ));
        $query = MeetingRequest::with(['units.unit', 'unitReviews.unit', 'requestingUnit', 'creator.employee', 'actions.actor'])
            ->where('flow_type', 'MULTI_DIRECTORATE')
            ->whereIn('status', [
                'SUBMITTED_TO_TARGETS',
                'UNDER_MULTI_TARGET_REVIEW',
                'TARGET_ACTION_REQUIRED',
                'MULTILATERAL_APPROVED',
                'SCHEDULING',
                'SCHEDULE_CONFIRMED',
                'CONVERTED_TO_MEETING',
            ])
            ->whereHas('units', fn ($q) => $q->where('role', 'TARGET')->whereIn('organizational_unit_id', $unitIds ?: [-1]));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        return MeetingRequestResource::collection($query->latest()->paginate(15))->additional(['success' => true, 'message' => 'درخواست‌های چندریاستی وارده.']);
    }

    public function update(UpdateMultiDirectorateMeetingRequest $request, MeetingRequest $meetingRequest, UpdateMultiDirectorateMeetingRequestAction $action)
    {
        $this->authorize('multiDirectorateRequesterEdit', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()), 'درخواست چندریاستی اصلاح شد.');
    }

    public function submit(Request $request, MeetingRequest $meetingRequest, SubmitMultiDirectorateRequest $action)
    {
        $this->authorize('multiDirectorateRequesterEdit', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست چندریاستی برای تأیید داخلی ارسال شد.');
    }

    public function requesterApprove(Request $request, MeetingRequest $meetingRequest, ApproveMultiRequesterSide $action)
    {
        $this->authorize('multiDirectorateRequesterApprove', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست چندریاستی تأیید و به همهٔ ریاست‌های مقصد ارسال شد.');
    }

    public function requesterReturn(MultiDirectorateReasonRequest $request, MeetingRequest $meetingRequest, ReturnMultiRequesterSide $action)
    {
        $this->authorize('multiDirectorateRequesterApprove', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['reason']), 'درخواست چندریاستی جهت اصلاح برگشت داده شد.');
    }

    public function requesterReject(MultiDirectorateReasonRequest $request, MeetingRequest $meetingRequest, RejectMultiRequesterSide $action)
    {
        $this->authorize('multiDirectorateRequesterApprove', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()['reason']), 'درخواست چندریاستی رد داخلی شد.');
    }

    public function targetReview(Request $request, MeetingRequest $meetingRequest, OrganizationalUnit $unit, StartMultiTargetReview $action)
    {
        $this->authorize('multiDirectorateTargetReview', [$meetingRequest, (int) $unit->id]);
        return $this->ok($action($request->user(), $meetingRequest, (int) $unit->id), 'بررسی این ریاست مقصد آغاز شد.');
    }

    public function targetApprove(Request $request, MeetingRequest $meetingRequest, OrganizationalUnit $unit, ApproveMultiTargetUnit $action)
    {
        $this->authorize('multiDirectorateTargetApprove', [$meetingRequest, (int) $unit->id]);
        return $this->ok($action($request->user(), $meetingRequest, (int) $unit->id), 'تأیید این ریاست مقصد ثبت شد.');
    }

    public function targetReturn(MultiDirectorateReasonRequest $request, MeetingRequest $meetingRequest, OrganizationalUnit $unit, ReturnMultiTargetUnit $action)
    {
        $this->authorize('multiDirectorateTargetReturn', [$meetingRequest, (int) $unit->id]);
        return $this->ok($action($request->user(), $meetingRequest, (int) $unit->id, $request->validated()['reason']), 'درخواست از سوی این ریاست مقصد جهت وضاحت برگشت داده شد.');
    }

    public function targetReject(MultiDirectorateReasonRequest $request, MeetingRequest $meetingRequest, OrganizationalUnit $unit, RejectMultiTargetUnit $action)
    {
        $this->authorize('multiDirectorateTargetReject', [$meetingRequest, (int) $unit->id]);
        return $this->ok($action($request->user(), $meetingRequest, (int) $unit->id, $request->validated()['reason']), 'درخواست توسط این ریاست مقصد رد شد.');
    }

    public function resubmit(Request $request, MeetingRequest $meetingRequest, ResubmitMultiDirectorateRequest $action)
    {
        $this->authorize('multiDirectorateRequesterEdit', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'درخواست چندریاستی دوباره ارسال شد.');
    }

    public function startScheduling(Request $request, MeetingRequest $meetingRequest, StartMultiDirectorateScheduling $action)
    {
        $this->authorize('multiDirectorateLeadCoordinate', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'مذاکرهٔ زمان‌بندی چندریاستی آغاز شد.');
    }

    public function proposeSchedule(MultiScheduleProposalRequest $request, MeetingRequest $meetingRequest, ProposeMultiSchedule $action)
    {
        $this->authorize('multiDirectorateCoordinate', [$meetingRequest, (int) $request->validated()['proposed_by_unit_id']]);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()), 'پیشنهاد زمان‌بندی چندریاستی ثبت شد.');
    }

    public function acceptSchedule(MultiScheduleAcceptanceRequest $request, MeetingRequest $meetingRequest, MeetingScheduleProposal $proposal, AcceptMultiScheduleProposal $action)
    {
        $unitId = $request->validated()['unit_id'] ?? null;
        if ($unitId !== null) $this->authorize('multiDirectorateCoordinate', [$meetingRequest, (int) $unitId]);
        else $this->authorize('multiDirectorateCoordinate', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $proposal, $unitId), 'پذیرش پیشنهاد زمان‌بندی ثبت شد.');
    }

    public function nominate(MultiParticipantRequest $request, MeetingRequest $meetingRequest, NominateMultiMeetingParticipant $action)
    {
        $this->authorize('multiDirectorateCoordinate', [$meetingRequest, (int) $request->validated()['organizational_unit_id']]);
        return $this->ok($action($request->user(), $meetingRequest, $request->validated()), 'اشتراک‌کنندهٔ این ریاست ثبت شد.');
    }

    public function confirmParticipants(Request $request, MeetingRequest $meetingRequest, OrganizationalUnit $unit, ConfirmUnitParticipants $action)
    {
        $this->authorize('multiDirectorateParticipantConfirm', [$meetingRequest, (int) $unit->id]);
        return $this->ok($action($request->user(), $meetingRequest, (int) $unit->id), 'فهرست اشتراک‌کنندگان این ریاست تأیید شد.');
    }

    public function confirmSchedule(Request $request, MeetingRequest $meetingRequest, ConfirmMultiDirectorateSchedule $action)
    {
        $this->authorize('multiDirectorateLeadCoordinate', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest), 'زمان‌بندی چندریاستی نهایی تأیید شد.');
    }

    public function finalize(FinalizeMultiDirectorateMeetingRequest $request, MeetingRequest $meetingRequest, ConvertMultiDirectorateRequestToMeeting $action)
    {
        $this->authorize('multiDirectorateLeadCoordinate', $meetingRequest);
        return (new MeetingResource($action($request->user(), $meetingRequest, $request->validated())))
            ->additional(['success' => true, 'message' => 'جلسهٔ چندریاستی ایجاد شد.']);
    }

    private function ok(MeetingRequest $request, string $message)
    {
        return (new MeetingRequestResource($request))->additional(['success' => true, 'message' => $message]);
    }
}
