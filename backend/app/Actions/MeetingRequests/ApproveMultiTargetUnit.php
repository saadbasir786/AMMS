<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveMultiTargetUnit
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId): MeetingRequest {
            $locked = $this->lockFlow03Request($request);
            $this->assertActiveActor($actor);
            $unit = $this->assertTargetMember($locked, $unitId);
            abort_unless($actor->can('meeting-request.approve-target') && $actor->hasScopedRole('DIRECTOR', $unit->id), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::UNDER_MULTI_TARGET_REVIEW->value, 422, 'TARGET_REVIEW_NOT_AVAILABLE');

            $review = $locked->unitReviews->firstWhere('organizational_unit_id', $unit->id);
            abort_unless($review, 422, 'TARGET_REVIEW_NOT_AVAILABLE');
            abort_if($review->review_status === 'APPROVED', 422, 'TARGET_UNIT_ALREADY_APPROVED');
            abort_if($review->review_status === 'REJECTED', 422, 'TARGET_UNIT_REJECTED');
            abort_unless($review->review_status === 'UNDER_REVIEW', 422, 'TARGET_REVIEW_NOT_AVAILABLE');

            $review->update(['review_status' => 'APPROVED', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'comment' => null]);
            $this->record($locked, $actor, 'TARGET_UNIT_APPROVED', $locked->status, $locked->status, null, ['unit_id' => $unit->id]);
            $this->notifyUser($locked->lead_coordinator_user_id, 'تأیید یک ریاست مقصد ثبت شد', $unit->name.' درخواست چندریاستی را تأیید کرد.', $locked);

            if ($this->allTargetsApproved($locked->fresh(['units.unit', 'unitReviews.unit']))) {
                $from = $this->updateStatus($locked, MeetingRequestStatus::MULTILATERAL_APPROVED);
                $this->record($locked, $actor, 'MULTILATERAL_APPROVED', $from, $locked->status, null, [
                    'approved_unit_ids' => $locked->units->where('role', 'TARGET')->pluck('organizational_unit_id')->values()->all(),
                ]);
                foreach ($this->participatingUnits($locked) as $participatingUnit) {
                    $this->notifyUnit($participatingUnit->id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'], 'تأیید چندجانبه تکمیل شد', 'همهٔ ریاست‌های مقصد درخواست را تأیید کردند و زمان‌بندی می‌تواند آغاز شود.', $locked);
                }
            }

            return $this->responseRequest($locked);
        });
    }
}
