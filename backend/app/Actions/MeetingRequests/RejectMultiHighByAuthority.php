<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectMultiHighByAuthority
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $locked = $this->lockMultiHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            $authority = $this->activeAuthorityFor($locked);
            $holder = $this->persistedHolder($locked);
            abort_unless($context && $this->canHighAuthority($actor, $holder, $authority), 403, 'UNAUTHORIZED_HIGH_AUTHORITY');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_HIGH_AUTHORITY_DECISION->value, 422, 'REQUEST_NOT_READY_FOR_HIGH_AUTHORITY');
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');
            $from = $this->transition($locked, MeetingRequestStatus::REJECTED_BY_HIGH_AUTHORITY);
            $context->update(['authority_decision_status' => 'REJECTED', 'authority_decided_by' => $actor->id, 'authority_decided_at' => now(), 'authority_decision_comment' => $reason]);
            $this->record($locked, $actor, 'HIGH_AUTHORITY_REJECTED', $from, $locked->status, $reason, ['authority_type' => $authority->authority_type, 'authority_position_id' => $authority->authority_position_id, 'authority_employee_id' => $holder->id, 'acted_by' => $actor->id, 'decision' => 'REJECTED']);
            return $this->responseRequest($locked);
        });
    }
}
