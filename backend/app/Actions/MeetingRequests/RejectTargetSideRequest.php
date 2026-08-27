<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectTargetSideRequest
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.reject-target'), 403, 'FORBIDDEN');
            abort_if(blank($reason), 422, 'REJECTION_REASON_REQUIRED');
            $locked = $this->lockInterDirectorateRequest($request);
            $target = $this->unitFor($locked, 'TARGET');
            abort_unless($actor->hasScopedRole('DIRECTOR', $target->id), 403, 'UNAUTHORIZED_TARGET_APPROVER');
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::UNDER_TARGET_REVIEW, 422, 'REQUEST_NOT_AVAILABLE_TO_TARGET');
            $from = $this->updateStatus($locked, MeetingRequestStatus::REJECTED_BY_TARGET);
            $this->record($locked, $actor, 'TARGET_REJECTED', $from, MeetingRequestStatus::REJECTED_BY_TARGET->value, $reason);
            $this->notifyUnit($this->unitFor($locked, 'REQUESTER')->id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'], 'درخواست توسط ریاست مقصد رد شد', $reason, $locked);
            return $this->responseRequest($locked);
        });
    }
}
