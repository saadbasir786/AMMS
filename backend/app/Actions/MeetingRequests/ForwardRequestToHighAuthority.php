<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ForwardRequestToHighAuthority
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow04Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canAuthorityOffice($actor, $context->authority_office_unit_id) && $actor->can('authority-request.forward'), 403, 'UNAUTHORIZED_AUTHORITY_OFFICE_USER');
            abort_unless($locked->status === MeetingRequestStatus::UNDER_AUTHORITY_OFFICE_REVIEW->value, 422, 'REQUEST_NOT_READY_FOR_AUTHORITY_OFFICE');
            abort_unless($context->office_review_status === 'UNDER_REVIEW', 422, 'AUTHORITY_OFFICE_REVIEW_REQUIRED');

            $authority = $this->activeAuthorityFor($locked);
            $holder = $this->currentAuthorityHolder($authority);
            $authorityUser = User::query()->where('employee_id', $holder->id)->where('status', 'ACTIVE')->first();
            abort_unless($authorityUser && $authorityUser->can('authority-request.decide'), 422, 'HIGH_AUTHORITY_HOLDER_NOT_AVAILABLE');

            $context->update([
                'organization_authority_id' => $authority->id,
                'authority_type' => $authority->authority_type,
                'authority_office_unit_id' => $authority->authority_office_unit_id,
                'authority_position_id' => $authority->authority_position_id,
                'authority_employee_id' => $holder->id,
                'office_review_status' => 'COMPLETED',
                'forwarded_by' => $actor->id,
                'forwarded_at' => now(),
                'authority_decision_status' => 'PENDING',
            ]);
            $from = $this->updateStatus($locked, MeetingRequestStatus::PENDING_HIGH_AUTHORITY_DECISION);
            $this->record($locked, $actor, 'FORWARDED_TO_HIGH_AUTHORITY', $from, $locked->status, null, ['authority_employee_id' => $holder->id, 'authority_position_id' => $authority->authority_position_id, 'authority_type' => $authority->authority_type]);
            $this->notifyUser($authorityUser->id, 'درخواست منتظر تصمیم مقام عالی', 'یک درخواست جلسه برای تصمیم مقام عالی ارسال شده است.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
