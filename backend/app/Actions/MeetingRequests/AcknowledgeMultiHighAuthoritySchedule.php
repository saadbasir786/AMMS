<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Models\MeetingAuthorityScheduleUnitAcknowledgement;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcknowledgeMultiHighAuthoritySchedule
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, MeetingScheduleProposal $proposal, int $unitId): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $proposal, $unitId): MeetingRequest {
            $locked = $this->lockMultiHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            $unit = $this->formalUnits($locked)->firstWhere('id', $unitId);
            abort_unless($unit, 403, 'PARTICIPATING_UNIT_NOT_PART_OF_REQUEST');
            abort_unless($proposal->meeting_request_id === $locked->id && in_array($proposal->status, ['PROPOSED', 'COUNTER_PROPOSED', 'ACCEPTED'], true), 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            abort_unless($actor->can('authority-schedule.acknowledge') && $this->canParticipatingUnit($actor, $unitId), 403, 'UNAUTHORIZED_PARTICIPATING_UNIT_ACTION');
            MeetingAuthorityScheduleUnitAcknowledgement::firstOrCreate(['meeting_request_id' => $locked->id, 'meeting_schedule_proposal_id' => $proposal->id, 'organizational_unit_id' => $unitId], ['acknowledged_by' => $actor->id, 'acknowledged_at' => now()]);
            $this->record($locked, $actor, 'DIRECTORATE_ACKNOWLEDGED_SCHEDULE', $locked->status, $locked->status, null, ['proposal_id' => $proposal->id, 'organizational_unit_id' => $unitId, 'unit_name' => $unit->name]);
            return $this->responseRequest($locked);
        });
    }
}
