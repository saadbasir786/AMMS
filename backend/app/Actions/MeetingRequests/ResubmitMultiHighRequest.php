<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResubmitMultiHighRequest
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockMultiHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            abort_unless($actor->id === $locked->created_by || $actor->id === $locked->lead_coordinator_user_id, 403, 'FORBIDDEN');
            abort_unless(in_array($locked->status, [MeetingRequestStatus::RETURNED_TO_REQUESTER->value, MeetingRequestStatus::DIRECTORATE_ACTION_REQUIRED->value, MeetingRequestStatus::RETURNED_BY_AUTHORITY_OFFICE->value, MeetingRequestStatus::RETURNED_BY_HIGH_AUTHORITY->value], true), 422, 'REQUEST_NOT_READY_FOR_RESUBMIT');
            $from = $locked->status;
            if ($from === MeetingRequestStatus::RETURNED_TO_REQUESTER->value || $from === MeetingRequestStatus::RETURNED_BY_HIGH_AUTHORITY->value) {
                $locked->update(['requester_approved_by' => null, 'requester_approved_at' => null]);
                $this->transition($locked, MeetingRequestStatus::PENDING_REQUESTER_APPROVAL);
            } elseif ($from === MeetingRequestStatus::DIRECTORATE_ACTION_REQUIRED->value) {
                $locked->unitReviews()->where('review_status', 'RETURNED_FOR_CLARIFICATION')->update(['review_status' => 'PENDING_REVIEW', 'reviewed_by' => null, 'reviewed_at' => null]);
                $this->transition($locked, MeetingRequestStatus::UNDER_PARTICIPATING_REVIEW);
            } else {
                $this->transition($locked, MeetingRequestStatus::SUBMITTED_TO_AUTHORITY_OFFICE);
            }
            $this->record($locked, $actor, 'PARTICIPATING_UNIT_RESUBMITTED', $from, $locked->status, null, ['flow_type' => 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY']);
            return $this->responseRequest($locked);
        });
    }
}
