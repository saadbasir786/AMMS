<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartInterDirectorateScheduling
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-schedule.propose'), 403, 'FORBIDDEN');
            $locked = $this->lockFlow02Request($request);
            $requester = $this->unitFor($locked, 'REQUESTER');
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::BILATERAL_APPROVED, 422, 'BILATERAL_APPROVAL_REQUIRED');
            abort_unless($this->canCoordinateUnit($actor, $requester->id), 403, 'REQUESTER_LEAD_REQUIRED');
            $from = $this->updateStatus($locked, MeetingRequestStatus::SCHEDULING);
            $this->record($locked, $actor, 'SCHEDULING_STARTED', $from, MeetingRequestStatus::SCHEDULING->value);
            return $this->responseRequest($locked);
        });
    }
}
