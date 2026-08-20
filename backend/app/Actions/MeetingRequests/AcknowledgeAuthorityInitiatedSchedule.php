<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcknowledgeAuthorityInitiatedSchedule
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId, int $proposalId): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId, $proposalId): MeetingRequest {
            $request = $this->lockFlow06Request($request);
            abort_unless($request->status === 'AUTHORITY_SCHEDULING', 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            abort_unless($request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists(), 403, 'DIRECTORATE_NOT_PART_OF_DIRECTIVE');
            abort_unless($actor->isActive() && $actor->can('authority-schedule.acknowledge') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $actor->hasScopedRole('DIRECTOR', $unitId)), 403, 'UNAUTHORIZED_DIRECTORATE_ACKNOWLEDGEMENT');
            $proposal = $request->scheduleProposals()->whereKey($proposalId)->where('status', 'PROPOSED')->firstOrFail();
            $request->authorityScheduleUnitAcknowledgements()->updateOrCreate([
                'meeting_schedule_proposal_id' => $proposal->id, 'organizational_unit_id' => $unitId,
            ], ['acknowledged_by' => $actor->id, 'acknowledged_at' => now()]);
            $this->record06($request, $actor, 'DIRECTORATE_SCHEDULE_ACKNOWLEDGED', $request->status, $request->status, null, ['proposal_id' => $proposal->id, 'organizational_unit_id' => $unitId]);
            return $this->response06($request);
        });
    }
}
