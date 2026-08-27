<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RejectHighAuthorityRequesterSide
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $locked = $this->lockHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            abort_unless($this->canRequesterApprove($actor, $locked->requesting_unit_id), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value, 422, 'REQUEST_NOT_PENDING_REQUESTER_APPROVAL');
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');

            $from = $this->updateStatus($locked, MeetingRequestStatus::REJECTED_INTERNAL);
            $this->record($locked, $actor, 'REQUESTER_REJECTED', $from, $locked->status, $reason, ['unit_id' => $locked->requesting_unit_id]);
            $this->notifyUser($locked->created_by, 'درخواست رد داخلی شد', 'درخواست جلسه با مقام عالی رد داخلی شد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
