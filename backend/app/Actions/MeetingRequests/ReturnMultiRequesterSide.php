<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnMultiRequesterSide
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): MeetingRequest {
            $locked = $this->lockMultiDirectorateRequest($request);
            $this->assertActiveActor($actor);
            $requester = $this->requesterUnit($locked);
            abort_unless($actor->can('meeting-request.return') && $actor->hasScopedRole('DIRECTOR', $requester->id), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value, 422, 'REQUEST_NOT_PENDING_REQUESTER_APPROVAL');
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');

            $from = $this->updateStatus($locked, MeetingRequestStatus::RETURNED_TO_REQUESTER);
            $this->record($locked, $actor, 'REQUESTER_RETURNED', $from, $locked->status, $reason, ['unit_id' => $requester->id]);
            $this->notifyUser($locked->lead_coordinator_user_id, 'درخواست چندریاستی جهت اصلاح برگشت داده شد', $reason, $locked);

            return $this->responseRequest($locked);
        });
    }
}
