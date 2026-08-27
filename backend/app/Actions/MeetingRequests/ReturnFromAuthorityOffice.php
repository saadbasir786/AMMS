<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnFromAuthorityOffice
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $locked = $this->lockHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canAuthorityOffice($actor, $context->authority_office_unit_id), 403, 'UNAUTHORIZED_AUTHORITY_OFFICE_USER');
            abort_unless($locked->status === MeetingRequestStatus::UNDER_AUTHORITY_OFFICE_REVIEW->value, 422, 'REQUEST_NOT_UNDER_AUTHORITY_OFFICE_REVIEW');
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');

            $from = $this->updateStatus($locked, MeetingRequestStatus::RETURNED_BY_AUTHORITY_OFFICE);
            $context->update(['office_review_status' => 'RETURNED', 'office_reviewed_by' => $actor->id, 'office_reviewed_at' => now(), 'office_review_comment' => $reason]);
            $this->record($locked, $actor, 'AUTHORITY_OFFICE_RETURNED', $from, $locked->status, $reason, ['authority_office_unit_id' => $context->authority_office_unit_id]);
            $this->notifyUser($locked->created_by, 'درخواست از دفتر مقام برگشت داده شد', $reason, $locked);
            $this->notifyUsersForUnit($locked->requesting_unit_id, ['EXECUTIVE_MANAGEMENT'], 'درخواست مقام عالی نیازمند اصلاح است', $reason, $locked);
            return $this->responseRequest($locked);
        });
    }
}
