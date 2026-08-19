<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectMultiTargetUnit
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId, $reason): MeetingRequest {
            $locked = $this->lockFlow03Request($request);
            $this->assertActiveActor($actor);
            $unit = $this->assertTargetMember($locked, $unitId);
            abort_unless($actor->can('meeting-request.reject-target') && $actor->hasScopedRole('DIRECTOR', $unit->id), 403, 'FORBIDDEN');
            abort_unless(in_array($locked->status, [MeetingRequestStatus::UNDER_MULTI_TARGET_REVIEW->value, MeetingRequestStatus::TARGET_ACTION_REQUIRED->value], true), 422, 'TARGET_REVIEW_NOT_AVAILABLE');
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');

            $review = $locked->unitReviews->firstWhere('organizational_unit_id', $unit->id);
            abort_unless($review && in_array($review->review_status, ['UNDER_REVIEW', 'PENDING_REVIEW'], true), 422, 'TARGET_REVIEW_NOT_AVAILABLE');
            $review->update(['review_status' => 'REJECTED', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'comment' => $reason]);

            $from = $this->updateStatus($locked, MeetingRequestStatus::REJECTED_BY_TARGET);
            $this->record($locked, $actor, 'TARGET_UNIT_REJECTED', $from, $locked->status, $reason, ['unit_id' => $unit->id]);
            $this->notifyUser($locked->lead_coordinator_user_id, 'درخواست چندریاستی توسط ریاست مقصد رد شد', $unit->name.': '.$reason, $locked);

            return $this->responseRequest($locked);
        });
    }
}
