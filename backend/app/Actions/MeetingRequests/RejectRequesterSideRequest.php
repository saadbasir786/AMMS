<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectRequesterSideRequest
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.reject'), 403, 'FORBIDDEN');
            abort_if(blank($reason), 422, 'REJECTION_REASON_REQUIRED');
            $locked = $this->lockInterDirectorateRequest($request);
            $requester = $this->unitFor($locked, 'REQUESTER');
            abort_unless($actor->hasScopedRole('DIRECTOR', $requester->id), 403, 'UNAUTHORIZED_REQUESTER_APPROVER');
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::PENDING_REQUESTER_APPROVAL, 422, 'REQUEST_NOT_PENDING_APPROVAL');
            $from = $this->updateStatus($locked, MeetingRequestStatus::REJECTED_INTERNAL);
            $this->record($locked, $actor, 'REQUESTER_REJECTED', $from, MeetingRequestStatus::REJECTED_INTERNAL->value, $reason);
            $this->notifyUser($locked->created_by, 'درخواست داخلی رد شد', $reason, $locked);
            return $this->responseRequest($locked);
        });
    }
}
