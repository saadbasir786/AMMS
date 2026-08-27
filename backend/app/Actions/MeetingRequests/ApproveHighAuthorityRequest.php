<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveHighAuthorityRequest
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, ?string $comment = null): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $comment): MeetingRequest {
            $locked = $this->lockHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context, 422, 'HIGH_AUTHORITY_NOT_CONFIGURED');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_HIGH_AUTHORITY_DECISION->value, 422, 'REQUEST_NOT_READY_FOR_HIGH_AUTHORITY');
            $authority = $this->activeAuthorityFor($locked);
            $holder = $this->assertPersistedHolderAvailable($locked);
            abort_unless($this->canHighAuthority($actor, $holder, $authority), 403, 'UNAUTHORIZED_HIGH_AUTHORITY_DECISION');
            abort_unless($context->authority_employee_id === $holder->id, 422, 'INVALID_HIGH_AUTHORITY_HOLDER');

            $from = $this->updateStatus($locked, MeetingRequestStatus::HIGH_AUTHORITY_APPROVED);
            $context->update(['authority_decision_status' => 'APPROVED', 'authority_decided_by' => $actor->id, 'authority_decided_at' => now(), 'authority_decision_comment' => $comment]);
            $this->record($locked, $actor, 'HIGH_AUTHORITY_APPROVED', $from, $locked->status, $comment, ['authority_employee_id' => $holder->id, 'authority_position_id' => $authority->authority_position_id, 'authority_type' => $authority->authority_type, 'decision' => 'APPROVED']);
            $this->notifyUsersForUnit($context->authority_office_unit_id, ['AUTHORITY_OFFICE', 'EXECUTIVE_SECRETARY'], 'تصمیم مقام عالی ثبت شد', 'مقام عالی درخواست جلسه را تأیید کرد؛ زمان‌بندی باید جداگانه انجام شود.', $locked);
            $this->notifyUser($locked->created_by, 'درخواست توسط مقام عالی تأیید شد', 'درخواست جلسه با مقام عالی تأیید شد و آمادهٔ زمان‌بندی است.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
