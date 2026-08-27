<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResubmitRequesterSideRequest
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.resubmit'), 403, 'FORBIDDEN');
            $locked = $this->lockInterDirectorateRequest($request);
            $requester = $this->unitFor($locked, 'REQUESTER');
            abort_unless($this->canManageUnit($actor, $requester->id), 403, 'FORBIDDEN');
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::RETURNED_TO_REQUESTER, 422, 'REQUEST_NOT_AVAILABLE_FOR_RESUBMISSION');

            $from = $this->updateStatus($locked, MeetingRequestStatus::PENDING_REQUESTER_APPROVAL);
            $this->record($locked, $actor, 'REQUESTER_RESUBMITTED', $from, MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value);
            $this->notifyUnit($requester->id, ['DIRECTOR'], 'درخواست اصلاح‌شده دوباره برای تأیید آماده است', 'درخواست بین‌ریاستی اصلاح و دوباره برای تأیید ریاست درخواست‌کننده ارسال شد.', $locked);

            return $this->responseRequest($locked);
        });
    }
}
