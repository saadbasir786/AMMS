<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveMultiHighParticipatingUnit
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId): MeetingRequest {
            $locked = $this->lockMultiHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $unit = $this->participatingUnits($locked)->firstWhere('id', $unitId);
            abort_unless($unit, 403, 'PARTICIPATING_UNIT_NOT_PART_OF_REQUEST');
            abort_unless($actor->can('meeting-request.approve-target') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('DIRECTOR', $unitId)), 403, 'UNAUTHORIZED_PARTICIPATING_UNIT_ACTION');
            $review = $locked->unitReviews()->where('organizational_unit_id', $unitId)->firstOrFail();
            abort_unless($review->review_status === 'UNDER_REVIEW', 422, 'PARTICIPATING_REVIEW_REQUIRED');
            $review->update(['review_status' => 'APPROVED', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'comment' => null]);
            $this->record($locked, $actor, 'PARTICIPATING_UNIT_APPROVED', $locked->status, $locked->status, null, ['organizational_unit_id' => $unitId, 'unit_name' => $unit->name]);
            if ($this->allParticipatingApproved($locked->fresh(['units.unit', 'unitReviews.unit']))) {
                $from = $this->transition($locked, MeetingRequestStatus::DIRECTORATES_APPROVED);
                $this->record($locked, $actor, 'DIRECTORATES_APPROVED', $from, $locked->status, null, ['unit_ids' => $this->participatingUnits($locked)->pluck('id')->all()]);
                $this->notifyUser($locked->lead_coordinator_user_id, 'تأیید همهٔ ریاست‌ها تکمیل شد', 'درخواست آمادهٔ ارسال به دفتر مقام است.', $locked);
            }
            return $this->responseRequest($locked);
        });
    }
}
