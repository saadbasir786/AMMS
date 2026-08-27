<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveMultiHighByAuthority
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $comment = ''): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $comment): MeetingRequest {
            $locked = $this->lockMultiHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            $authority = $this->activeAuthorityFor($locked);
            $holder = $this->persistedHolder($locked);
            abort_unless($context && $this->canHighAuthority($actor, $holder, $authority), 403, 'UNAUTHORIZED_HIGH_AUTHORITY');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_HIGH_AUTHORITY_DECISION->value, 422, 'REQUEST_NOT_READY_FOR_HIGH_AUTHORITY');
            $from = $this->transition($locked, MeetingRequestStatus::HIGH_AUTHORITY_APPROVED);
            $context->update(['authority_decision_status' => 'APPROVED', 'authority_decided_by' => $actor->id, 'authority_decided_at' => now(), 'authority_decision_comment' => $comment ?: null]);
            $this->record($locked, $actor, 'HIGH_AUTHORITY_APPROVED', $from, $locked->status, $comment ?: null, ['authority_type' => $authority->authority_type, 'authority_position_id' => $authority->authority_position_id, 'authority_employee_id' => $holder->id, 'acted_by' => $actor->id, 'decision' => 'APPROVED']);
            $this->notifyUser($locked->lead_coordinator_user_id, 'مقام عالی درخواست را تأیید کرد', 'درخواست وارد مرحلهٔ زمان‌بندی رسمی شد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
