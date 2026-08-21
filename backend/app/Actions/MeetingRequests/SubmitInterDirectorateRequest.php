<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitInterDirectorateRequest
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.submit') || $actor->can('meeting-request.resubmit'), 403, 'FORBIDDEN');
            $locked = $this->lockFlow02Request($request);
            $requester = $this->unitFor($locked, 'REQUESTER');
            $target = $this->unitFor($locked, 'TARGET');
            abort_unless($this->canManageUnit($actor, $requester->id), 403, 'FORBIDDEN');

            $current = MeetingRequestStatus::tryFrom($locked->status);
            abort_unless(in_array($current, [MeetingRequestStatus::DRAFT, MeetingRequestStatus::RETURNED_TO_REQUESTER], true), 422, 'REQUEST_ALREADY_SUBMITTED');
            $this->ensureSameOrganizationAndDistinct($requester, $target);
            abort_if(blank($locked->subject) || blank($locked->purpose), 422, 'VALIDATION_ERROR');
            $this->ensureAuthorizedApproverExists($requester->id);

            $from = $this->updateStatus($locked, MeetingRequestStatus::PENDING_REQUESTER_APPROVAL);
            $locked->update(['request_number' => $locked->request_number ?: $this->nextNumber('meeting_requests', 'MR')]);
            $action = $current === MeetingRequestStatus::RETURNED_TO_REQUESTER ? 'REQUESTER_RESUBMITTED' : 'SUBMITTED_FOR_REQUESTER_APPROVAL';
            $this->record($locked, $actor, $action, $from, MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value);
            $this->notifyUnit($requester->id, ['DIRECTOR'], 'درخواست بین‌ریاستی برای تأیید داخلی', 'یک درخواست جلسهٔ بین دو ریاست منتظر تأیید ریاست شما است.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
