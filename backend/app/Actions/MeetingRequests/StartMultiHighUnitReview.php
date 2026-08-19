<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartMultiHighUnitReview
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $unit = $this->participatingUnits($locked)->firstWhere('id', $unitId);
            abort_unless($unit, 403, 'PARTICIPATING_UNIT_NOT_PART_OF_REQUEST');
            abort_unless($this->canParticipatingUnit($actor, $unitId), 403, 'UNAUTHORIZED_PARTICIPATING_UNIT_ACTION');
            abort_unless(in_array($locked->status, [MeetingRequestStatus::SUBMITTED_TO_PARTICIPATING_DIRECTORATES->value, MeetingRequestStatus::UNDER_PARTICIPATING_REVIEW->value, MeetingRequestStatus::DIRECTORATE_ACTION_REQUIRED->value], true), 422, 'PARTICIPATING_REVIEW_REQUIRED');
            $review = $locked->unitReviews()->where('organizational_unit_id', $unitId)->firstOrFail();
            abort_unless(in_array($review->review_status, ['PENDING_REVIEW', 'RETURNED_FOR_CLARIFICATION'], true), 422, 'PARTICIPATING_REVIEW_ALREADY_STARTED');
            $review->update(['review_status' => 'UNDER_REVIEW', 'reviewed_by' => null, 'reviewed_at' => null]);
            if ($locked->status === MeetingRequestStatus::SUBMITTED_TO_PARTICIPATING_DIRECTORATES->value) $this->transition($locked, MeetingRequestStatus::UNDER_PARTICIPATING_REVIEW);
            $this->record($locked, $actor, 'PARTICIPATING_UNIT_REVIEW_STARTED', $locked->status, $locked->status, null, ['organizational_unit_id' => $unitId, 'unit_name' => $unit->name]);
            return $this->responseRequest($locked);
        });
    }
}
