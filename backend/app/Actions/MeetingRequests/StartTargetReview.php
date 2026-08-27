<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartTargetReview
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.view-incoming'), 403, 'FORBIDDEN');
            $locked = $this->lockInterDirectorateRequest($request);
            $target = $this->unitFor($locked, 'TARGET');
            abort_unless($actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $target->id) || $actor->hasScopedRole('DIRECTOR', $target->id), 403, 'REQUEST_NOT_AVAILABLE_TO_TARGET');
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::SUBMITTED_TO_TARGET, 422, 'REQUEST_NOT_AVAILABLE_TO_TARGET');
            $from = $this->updateStatus($locked, MeetingRequestStatus::UNDER_TARGET_REVIEW);
            $this->record($locked, $actor, 'TARGET_REVIEW_STARTED', $from, MeetingRequestStatus::UNDER_TARGET_REVIEW->value);
            return $this->responseRequest($locked);
        });
    }
}
