<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmMultiHighUnitParticipants
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $unit = $this->formalUnits($locked)->firstWhere('id', $unitId);
            abort_unless($unit, 403, 'PARTICIPATING_UNIT_NOT_PART_OF_REQUEST');
            abort_unless($actor->can('meeting-participant.confirm') && $this->canParticipatingUnit($actor, $unitId), 403, 'UNAUTHORIZED_PARTICIPATING_UNIT_ACTION');
            abort_unless($locked->status === 'SCHEDULE_CONFIRMED', 422, 'AUTHORITY_SCHEDULE_NOT_CONFIRMED');
            $hasFinal = $locked->nominations()->where('participant_stage', 'FINAL')->where('nomination_status', 'NOMINATED')->where('nominated_by_unit_id', $unitId)->exists();
            abort_unless($hasFinal, 422, 'ALL_UNIT_PARTICIPANTS_REQUIRED');
            $locked->participantConfirmations()->updateOrCreate(['organizational_unit_id' => $unitId], ['confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            $locked->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $unitId)->update(['nomination_status' => 'CONFIRMED']);
            $this->record($locked, $actor, 'UNIT_PARTICIPANTS_CONFIRMED', $locked->status, $locked->status, null, ['organizational_unit_id' => $unitId, 'unit_name' => $unit->name]);
            return $this->responseRequest($locked);
        });
    }
}
