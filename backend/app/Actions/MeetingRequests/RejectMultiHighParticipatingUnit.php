<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectMultiHighParticipatingUnit
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId, $reason): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $unit = $this->participatingUnits($locked)->firstWhere('id', $unitId);
            abort_unless($unit, 403, 'PARTICIPATING_UNIT_NOT_PART_OF_REQUEST');
            abort_unless($actor->can('meeting-request.reject-target') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('DIRECTOR', $unitId)), 403, 'UNAUTHORIZED_PARTICIPATING_UNIT_ACTION');
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');
            $review = $locked->unitReviews()->where('organizational_unit_id', $unitId)->firstOrFail();
            abort_unless(in_array($review->review_status, ['UNDER_REVIEW', 'APPROVED'], true), 422, 'PARTICIPATING_REVIEW_REQUIRED');
            $review->update(['review_status' => 'REJECTED', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'comment' => $reason]);
            $from = $this->transition($locked, MeetingRequestStatus::REJECTED_BY_PARTICIPATING_DIRECTORATE);
            $this->record($locked, $actor, 'PARTICIPATING_UNIT_REJECTED', $from, $locked->status, $reason, ['organizational_unit_id' => $unitId, 'unit_name' => $unit->name]);
            return $this->responseRequest($locked);
        });
    }
}
