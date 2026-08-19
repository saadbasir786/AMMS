<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ForwardMultiHighToHighAuthority
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canAuthorityOffice($actor, $context->authority_office_unit_id), 403, 'UNAUTHORIZED_AUTHORITY_OFFICE_USER');
            abort_unless($locked->status === MeetingRequestStatus::UNDER_AUTHORITY_OFFICE_REVIEW->value, 422, 'AUTHORITY_OFFICE_REVIEW_REQUIRED');
            abort_unless($this->allParticipatingApproved($locked) && $locked->requester_approved_at, 422, 'ALL_DIRECTORATE_APPROVALS_REQUIRED');
            $authority = $this->activeAuthorityFor($locked);
            $holder = $this->currentAuthorityHolder($authority);
            $from = $this->transition($locked, MeetingRequestStatus::PENDING_HIGH_AUTHORITY_DECISION);
            $context->update(['authority_employee_id' => $holder->id, 'office_review_status' => 'COMPLETED', 'office_reviewed_by' => $actor->id, 'office_reviewed_at' => now(), 'forwarded_by' => $actor->id, 'forwarded_at' => now()]);
            $this->record($locked, $actor, 'FORWARDED_TO_HIGH_AUTHORITY', $from, $locked->status, null, ['authority_type' => $authority->authority_type, 'authority_position_id' => $authority->authority_position_id, 'authority_employee_id' => $holder->id, 'authority_office_unit_id' => $authority->authority_office_unit_id]);
            $this->notifyUser(User::where('employee_id', $holder->id)->value('id'), 'درخواست جدید برای تصمیم مقام عالی', 'درخواست چندریاستی برای تصمیم رسمی ارسال شد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
