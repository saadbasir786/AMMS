<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartMultiTargetReview
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId): MeetingRequest {
            $locked = $this->lockMultiDirectorateRequest($request);
            $this->assertActiveActor($actor);
            $unit = $this->assertTargetMember($locked, $unitId);
            abort_unless($this->canManageUnit($actor, $unit->id), 403, 'FORBIDDEN');
            abort_unless(in_array($locked->status, [MeetingRequestStatus::SUBMITTED_TO_TARGETS->value, MeetingRequestStatus::UNDER_MULTI_TARGET_REVIEW->value], true), 422, 'TARGET_REVIEW_NOT_AVAILABLE');

            $review = $locked->unitReviews->firstWhere('organizational_unit_id', $unit->id);
            abort_unless($review && $review->review_status === 'PENDING_REVIEW', 422, 'TARGET_REVIEW_NOT_AVAILABLE');
            $review->update(['review_status' => 'UNDER_REVIEW']);

            $from = $locked->status;
            if ($locked->status === MeetingRequestStatus::SUBMITTED_TO_TARGETS->value) {
                $this->updateStatus($locked, MeetingRequestStatus::UNDER_MULTI_TARGET_REVIEW);
            }
            $this->record($locked, $actor, 'TARGET_REVIEW_STARTED', $from, $locked->status, null, ['unit_id' => $unit->id]);

            return $this->responseRequest($locked);
        });
    }
}
