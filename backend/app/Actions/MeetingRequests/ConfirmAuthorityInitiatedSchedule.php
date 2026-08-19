<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmAuthorityInitiatedSchedule
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $request = $this->lockFlow06Request($request);
            $officeId = (int) ($request->authorityContext?->authority_office_unit_id ?? 0);
            abort_unless($officeId && $actor->can('authority-schedule.confirm') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeId)), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($request->status === MeetingRequestStatus::AUTHORITY_SCHEDULING->value, 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            $proposal = $request->scheduleProposals()->where('status', 'PROPOSED')->latest('id')->first();
            abort_unless($proposal, 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            $unitIds = $this->flow06Units($request)->pluck('id')->map(fn ($id) => (int) $id);
            $acks = $request->authorityScheduleUnitAcknowledgements()->where('meeting_schedule_proposal_id', $proposal->id)->whereIn('organizational_unit_id', $unitIds)->count();
            abort_unless($acks === $unitIds->count(), 422, 'AUTHORITY_SCHEDULE_NOT_ACKNOWLEDGED_BY_ALL');
            abort_unless($proposal->status !== 'ALTERNATIVE_REQUESTED', 422, 'UNRESOLVED_ALTERNATIVE_SCHEDULE_REQUEST');
            $proposal->update(['status' => 'ACCEPTED']);
            $from = $this->transition06($request, MeetingRequestStatus::SCHEDULE_CONFIRMED);
            $request->update(['schedule_confirmed_by' => $actor->id, 'schedule_confirmed_at' => now()]);
            $this->record06($request, $actor, 'AUTHORITY_SCHEDULE_CONFIRMED', $from, MeetingRequestStatus::SCHEDULE_CONFIRMED->value, null, ['proposal_id' => $proposal->id]);
            return $this->response06($request);
        });
    }
}
