<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnByHighAuthority
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $locked = $this->lockHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context, 422, 'HIGH_AUTHORITY_NOT_CONFIGURED');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_HIGH_AUTHORITY_DECISION->value, 422, 'REQUEST_NOT_READY_FOR_HIGH_AUTHORITY');
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');
            $authority = $this->activeAuthorityFor($locked);
            $holder = $this->assertPersistedHolderAvailable($locked);
            abort_unless($this->canHighAuthority($actor, $holder, $authority), 403, 'UNAUTHORIZED_HIGH_AUTHORITY_DECISION');

            $from = $this->updateStatus($locked, MeetingRequestStatus::RETURNED_BY_HIGH_AUTHORITY);
            $context->update(['authority_decision_status' => 'RETURNED', 'authority_decided_by' => $actor->id, 'authority_decided_at' => now(), 'authority_decision_comment' => $reason]);
            $this->record($locked, $actor, 'HIGH_AUTHORITY_RETURNED', $from, $locked->status, $reason, ['authority_employee_id' => $holder->id, 'decision' => 'RETURNED']);
            $this->notifyUser($locked->created_by, 'درخواست توسط مقام عالی برگشت داده شد', $reason, $locked);
            $this->notifyUsersForUnit($locked->requesting_unit_id, ['EXECUTIVE_MANAGEMENT'], 'درخواست مقام عالی نیازمند اصلاح است', $reason, $locked);
            return $this->responseRequest($locked);
        });
    }
}
