<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestAuthorityInitiatedScheduleAlternative
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId, int $proposalId, string $reason): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId, $proposalId, $reason): MeetingRequest {
            $request = $this->lockFlow06Request($request);
            abort_unless(filled(trim($reason)), 422, 'REASON_REQUIRED');
            abort_unless($request->status === 'AUTHORITY_SCHEDULING', 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            abort_unless($request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists(), 403, 'DIRECTORATE_NOT_PART_OF_DIRECTIVE');
            abort_unless($actor->isActive() && $actor->can('authority-schedule.acknowledge') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('DIRECTOR', $unitId)), 403, 'UNAUTHORIZED_DIRECTORATE_ACKNOWLEDGEMENT');
            $proposal = $request->scheduleProposals()->whereKey($proposalId)->where('status', 'PROPOSED')->firstOrFail();
            $proposal->update(['status' => 'ALTERNATIVE_REQUESTED']);
            $this->record06($request, $actor, 'DIRECTORATE_SCHEDULE_ALTERNATIVE_REQUESTED', $request->status, $request->status, $reason, ['proposal_id' => $proposal->id, 'organizational_unit_id' => $unitId]);
            return $this->response06($request);
        });
    }
}
