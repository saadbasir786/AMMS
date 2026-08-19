<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmRequesterParticipants
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return $this->confirm($actor, $request, 'REQUESTER');
    }

    private function confirm(User $actor, MeetingRequest $request, string $sideRole): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $sideRole): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-participant.confirm'), 403, 'FORBIDDEN');
            $locked = $this->lockFlow02Request($request);
            abort_unless(in_array($locked->status, ['BILATERAL_APPROVED', 'SCHEDULING'], true), 422, 'BILATERAL_APPROVAL_REQUIRED');
            $unit = $this->unitFor($locked, $sideRole);
            abort_unless($this->canCoordinateUnit($actor, $unit->id), 403, 'FORBIDDEN');
            abort_if($locked->requester_participants_confirmed_at, 422, 'PARTICIPANTS_ALREADY_CONFIRMED');
            abort_unless($locked->nominations()->where('nominated_by_unit_id', $unit->id)->where('participant_stage', 'FINAL')->exists(), 422, 'PARTICIPANTS_REQUIRED');
            $locked->nominations()->where('nominated_by_unit_id', $unit->id)->where('participant_stage', 'FINAL')->update(['nomination_status' => 'CONFIRMED']);
            $locked->update(['requester_participants_confirmed_by' => $actor->id, 'requester_participants_confirmed_at' => now()]);
            $this->record($locked, $actor, 'REQUESTER_PARTICIPANTS_CONFIRMED', $locked->status, $locked->status, null, ['unit_id' => $unit->id]);
            $this->notifyUser($locked->lead_coordinator_user_id, 'اشتراک‌کنندگان ریاست درخواست‌کننده تأیید شد', 'ریاست درخواست‌کننده فهرست نهایی اشتراک‌کنندگان را تأیید کرد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
