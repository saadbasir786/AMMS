<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Models\MeetingAuthorityScheduleAcknowledgement;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcknowledgeAuthoritySchedule
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, MeetingScheduleProposal $proposal): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $proposal): MeetingRequest {
            $locked = $this->lockFlow04Request($request);
            $this->assertActiveActor($actor);
            abort_unless($locked->status === 'AUTHORITY_SCHEDULING', 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            abort_unless($proposal->meeting_request_id === $locked->id, 404, 'PROPOSAL_NOT_FOUND');
            abort_unless(in_array($proposal->proposal_type, ['AUTHORITY_OFFICIAL_PROPOSAL', 'AUTHORITY_REVISED_PROPOSAL'], true) && $proposal->status === 'PROPOSED', 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            abort_unless($this->canManageRequester($actor, $locked->requesting_unit_id) || $actor->hasScopedRole('MEETING_COORDINATOR', $locked->requesting_unit_id), 403, 'FORBIDDEN');
            abort_if(MeetingAuthorityScheduleAcknowledgement::where('meeting_schedule_proposal_id', $proposal->id)->where('requesting_unit_id', $locked->requesting_unit_id)->exists(), 422, 'AUTHORITY_SCHEDULE_ALREADY_ACKNOWLEDGED');

            MeetingAuthorityScheduleAcknowledgement::create([
                'meeting_request_id' => $locked->id,
                'meeting_schedule_proposal_id' => $proposal->id,
                'requesting_unit_id' => $locked->requesting_unit_id,
                'acknowledged_by' => $actor->id,
                'acknowledged_at' => now(),
            ]);
            $this->record($locked, $actor, 'REQUESTER_ACKNOWLEDGED_SCHEDULE', $locked->status, $locked->status, null, ['proposal_id' => $proposal->id, 'requesting_unit_id' => $locked->requesting_unit_id]);
            return $this->responseRequest($locked);
        });
    }
}
