<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnMultiHighParticipatingUnit
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId, $reason): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $unit = $this->participatingUnits($locked)->firstWhere('id', $unitId);
            abort_unless($unit, 403, 'PARTICIPATING_UNIT_NOT_PART_OF_REQUEST');
            abort_unless($actor->can('meeting-request.return-target') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('DIRECTOR', $unitId)), 403, 'UNAUTHORIZED_PARTICIPATING_UNIT_ACTION');
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');
            $review = $locked->unitReviews()->where('organizational_unit_id', $unitId)->firstOrFail();
            abort_unless(in_array($review->review_status, ['UNDER_REVIEW', 'APPROVED'], true), 422, 'PARTICIPATING_REVIEW_REQUIRED');
            $review->update(['review_status' => 'RETURNED_FOR_CLARIFICATION', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'comment' => $reason]);
            $from = $locked->status;
            if ($locked->status !== MeetingRequestStatus::DIRECTORATE_ACTION_REQUIRED->value) $this->transition($locked, MeetingRequestStatus::DIRECTORATE_ACTION_REQUIRED);
            $this->record($locked, $actor, 'PARTICIPATING_UNIT_RETURNED', $from, $locked->status, $reason, ['organizational_unit_id' => $unitId, 'unit_name' => $unit->name]);
            $this->notifyUser($locked->lead_coordinator_user_id, 'درخواست از سوی ریاست اشتراک‌کننده برگشت داده شد', $reason, $locked);
            return $this->responseRequest($locked);
        });
    }
}
