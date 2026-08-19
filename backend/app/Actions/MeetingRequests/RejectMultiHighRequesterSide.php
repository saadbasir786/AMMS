<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectMultiHighRequesterSide
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            abort_unless($this->canRequesterApprove($actor, $this->requesterUnit($locked)->id), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value, 422, 'REQUEST_NOT_READY_FOR_REQUESTER_APPROVAL');
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');
            $from = $this->transition($locked, MeetingRequestStatus::REJECTED_INTERNAL);
            $this->record($locked, $actor, 'REQUESTER_REJECTED', $from, $locked->status, $reason, ['organizational_unit_id' => $this->requesterUnit($locked)->id]);
            return $this->responseRequest($locked);
        });
    }
}
