<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Models\MeetingAuthorityParticipantConfirmation;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmHighAuthorityRequesterParticipants
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow04Request($request);
            $this->assertActiveActor($actor);
            abort_unless(in_array($locked->status, ['AUTHORITY_SCHEDULING', 'SCHEDULE_CONFIRMED'], true), 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            abort_unless($this->canManageRequester($actor, $locked->requesting_unit_id) || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $locked->requesting_unit_id), 403, 'FORBIDDEN');
            abort_unless($locked->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $locked->requesting_unit_id)->exists(), 422, 'REQUESTER_PARTICIPANTS_NOT_CONFIRMED');
            abort_if(MeetingAuthorityParticipantConfirmation::where('meeting_request_id', $locked->id)->where('side', 'REQUESTER')->exists(), 422, 'REQUESTER_PARTICIPANTS_ALREADY_CONFIRMED');

            MeetingAuthorityParticipantConfirmation::create(['meeting_request_id' => $locked->id, 'side' => 'REQUESTER', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            $this->record($locked, $actor, 'REQUESTER_PARTICIPANTS_CONFIRMED', $locked->status, $locked->status, null, ['requesting_unit_id' => $locked->requesting_unit_id]);
            return $this->responseRequest($locked);
        });
    }
}
