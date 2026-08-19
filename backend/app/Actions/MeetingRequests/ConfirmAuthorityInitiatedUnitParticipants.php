<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmAuthorityInitiatedUnitParticipants
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId): MeetingRequest {
            $request = $this->lockFlow06Request($request);
            abort_unless($request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists(), 403, 'DIRECTORATE_NOT_PART_OF_DIRECTIVE');
            abort_unless($actor->isActive() && $actor->can('meeting-participant.confirm') && $this->actorOwnsDirectorate($actor, $unitId), 403, 'UNAUTHORIZED_PARTICIPATING_UNIT_ACTION');
            abort_unless(in_array($request->status, ['AUTHORITY_SCHEDULING', 'SCHEDULE_CONFIRMED'], true), 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            abort_unless($request->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $unitId)->exists(), 422, 'UNIT_PARTICIPANTS_NOT_CONFIRMED');
            $request->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $unitId)->update(['nomination_status' => 'CONFIRMED']);
            $request->participantConfirmations()->updateOrCreate(['organizational_unit_id' => $unitId], ['confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            $this->record06($request, $actor, 'UNIT_PARTICIPANTS_CONFIRMED', $request->status, $request->status, null, ['organizational_unit_id' => $unitId]);
            return $this->response06($request);
        });
    }
}
