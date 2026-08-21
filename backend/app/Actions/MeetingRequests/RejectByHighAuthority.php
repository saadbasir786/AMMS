<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectByHighAuthority
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $locked = $this->lockFlow04Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context, 422, 'HIGH_AUTHORITY_NOT_CONFIGURED');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_HIGH_AUTHORITY_DECISION->value, 422, 'REQUEST_NOT_READY_FOR_HIGH_AUTHORITY');
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');
            $authority = $this->activeAuthorityFor($locked);
            $holder = $this->assertPersistedHolderAvailable($locked);
            abort_unless($this->canHighAuthority($actor, $holder, $authority), 403, 'UNAUTHORIZED_HIGH_AUTHORITY_DECISION');

            $from = $this->updateStatus($locked, MeetingRequestStatus::REJECTED_BY_HIGH_AUTHORITY);
            $context->update(['authority_decision_status' => 'REJECTED', 'authority_decided_by' => $actor->id, 'authority_decided_at' => now(), 'authority_decision_comment' => $reason]);
            $this->record($locked, $actor, 'HIGH_AUTHORITY_REJECTED', $from, $locked->status, $reason, ['authority_employee_id' => $holder->id, 'decision' => 'REJECTED']);
            $this->notifyUser($locked->created_by, 'درخواست توسط مقام عالی رد شد', $reason, $locked);
            $this->notifyUsersForUnit($context->authority_office_unit_id, ['AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY'], 'درخواست توسط مقام عالی رد شد', $reason, $locked);
            return $this->responseRequest($locked);
        });
    }
}
