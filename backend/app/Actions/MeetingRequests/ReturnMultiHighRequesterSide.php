<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnMultiHighRequesterSide
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            abort_unless($this->canRequesterApprove($actor, $this->requesterUnit($locked)->id), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value && filled(trim($reason)), 422, filled(trim($reason)) ? 'REQUEST_NOT_READY_FOR_REQUESTER_APPROVAL' : 'REASON_REQUIRED');
            $from = $this->transition($locked, MeetingRequestStatus::RETURNED_TO_REQUESTER);
            $locked->update(['requester_approved_by' => null, 'requester_approved_at' => null]);
            $this->record($locked, $actor, 'REQUESTER_RETURNED', $from, $locked->status, $reason, ['organizational_unit_id' => $this->requesterUnit($locked)->id]);
            $this->notifyUser($locked->created_by, 'درخواست برای اصلاح برگشت داده شد', $reason, $locked);
            return $this->responseRequest($locked);
        });
    }
}
