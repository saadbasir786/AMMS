<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartMultiHighAuthorityOfficeReview
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canAuthorityOffice($actor, $context->authority_office_unit_id), 403, 'UNAUTHORIZED_AUTHORITY_OFFICE_USER');
            abort_unless($locked->status === MeetingRequestStatus::SUBMITTED_TO_AUTHORITY_OFFICE->value, 422, 'REQUEST_NOT_READY_FOR_AUTHORITY_OFFICE');
            $from = $this->transition($locked, MeetingRequestStatus::UNDER_AUTHORITY_OFFICE_REVIEW);
            $context->update(['office_review_status' => 'UNDER_REVIEW', 'office_reviewed_by' => $actor->id, 'office_reviewed_at' => now()]);
            $this->record($locked, $actor, 'AUTHORITY_OFFICE_REVIEW_STARTED', $from, $locked->status, null, ['authority_office_unit_id' => $context->authority_office_unit_id]);
            return $this->responseRequest($locked);
        });
    }
}
