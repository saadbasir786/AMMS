<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveMultiRequesterSide
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockMultiDirectorateRequest($request);
            $this->assertActiveActor($actor);
            $requester = $this->requesterUnit($locked);
            abort_unless($actor->can('meeting-request.approve') && $actor->hasScopedRole('DIRECTOR', $requester->id), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value, 422, 'REQUEST_NOT_PENDING_REQUESTER_APPROVAL');

            $from = $this->updateStatus($locked, MeetingRequestStatus::REQUESTER_APPROVED);
            $locked->update(['requester_approved_by' => $actor->id, 'requester_approved_at' => now(), 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->record($locked, $actor, 'REQUESTER_APPROVED', $from, $locked->status, null, ['unit_id' => $requester->id]);

            $from = $this->updateStatus($locked, MeetingRequestStatus::SUBMITTED_TO_TARGETS);
            $this->record($locked, $actor, 'REQUEST_SENT_TO_TARGETS', $from, $locked->status, null, [
                'target_unit_ids' => $locked->units->where('role', 'TARGET')->pluck('organizational_unit_id')->values()->all(),
            ]);
            foreach ($this->targetUnits($locked) as $target) {
                $this->notifyUnit($target->id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'], 'درخواست چندریاستی وارده', 'یک درخواست جلسهٔ چندریاستی برای ریاست شما ارسال شده است.', $locked);
            }

            return $this->responseRequest($locked);
        });
    }
}
