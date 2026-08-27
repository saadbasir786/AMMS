<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnTargetSideRequest
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.return-target'), 403, 'FORBIDDEN');
            abort_if(blank($reason), 422, 'RETURN_REASON_REQUIRED');
            $locked = $this->lockInterDirectorateRequest($request);
            $target = $this->unitFor($locked, 'TARGET');
            abort_unless($actor->hasScopedRole('DIRECTOR', $target->id), 403, 'UNAUTHORIZED_TARGET_APPROVER');
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::UNDER_TARGET_REVIEW, 422, 'REQUEST_NOT_AVAILABLE_TO_TARGET');
            $from = $this->updateStatus($locked, MeetingRequestStatus::RETURNED_BY_TARGET);
            $this->record($locked, $actor, 'TARGET_RETURNED', $from, MeetingRequestStatus::RETURNED_BY_TARGET->value, $reason);
            $this->notifyUser($locked->created_by, 'درخواست از ریاست مقصد برگشت داده شد', $reason, $locked);
            return $this->responseRequest($locked);
        });
    }
}
