<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveMultiHighRequesterSide
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $requester = $this->requesterUnit($locked);
            abort_unless($this->canRequesterApprove($actor, $requester->id), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value, 422, 'REQUEST_NOT_READY_FOR_REQUESTER_APPROVAL');
            $from = $this->transition($locked, MeetingRequestStatus::REQUESTER_APPROVED);
            $locked->update(['requester_approved_by' => $actor->id, 'requester_approved_at' => now()]);
            $this->record($locked, $actor, 'REQUESTER_APPROVED', $from, $locked->status, null, ['organizational_unit_id' => $requester->id, 'unit_name' => $requester->name]);
            $from = $this->transition($locked, MeetingRequestStatus::SUBMITTED_TO_PARTICIPATING_DIRECTORATES);
            $this->record($locked, $actor, 'REQUEST_SENT_TO_PARTICIPATING_DIRECTORATES', $from, $locked->status, null, ['unit_ids' => $this->participatingUnits($locked)->pluck('id')->all()]);
            foreach ($this->participatingUnits($locked) as $unit) $this->notifyUnit($unit->id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR', 'MEETING_COORDINATOR'], 'درخواست چندریاستی جدید', 'درخواست پس از تأیید ریاست درخواست‌کننده برای بررسی ارسال شد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
