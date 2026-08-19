<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmMultiHighAuthoritySchedule
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canScheduler($actor, $context->authority_office_unit_id), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($locked->status === MeetingRequestStatus::AUTHORITY_SCHEDULING->value, 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            $proposal = $locked->scheduleProposals()->whereIn('status', ['PROPOSED', 'COUNTER_PROPOSED', 'ACCEPTED'])->latest('id')->first();
            abort_unless($proposal, 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            abort_unless(!$locked->scheduleProposals()->where('id', $proposal->id)->where('status', 'ALTERNATIVE_REQUESTED')->exists(), 422, 'AUTHORITY_SCHEDULE_ALTERNATIVE_PENDING');
            $unitIds = $this->formalUnits($locked)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $ackIds = $locked->authorityScheduleUnitAcknowledgements()->where('meeting_schedule_proposal_id', $proposal->id)->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->unique()->sort()->values();
            abort_unless($ackIds->count() === $unitIds->count() && $ackIds->diff($unitIds)->isEmpty(), 422, 'ALL_DIRECTORATE_SCHEDULE_ACKNOWLEDGEMENTS_REQUIRED');
            $proposal->update(['status' => 'ACCEPTED']);
            $from = $this->transition($locked, MeetingRequestStatus::SCHEDULE_CONFIRMED);
            $locked->update(['preferred_date' => $proposal->meeting_date, 'preferred_start_time' => $proposal->start_time, 'proposed_location' => $proposal->location, 'schedule_confirmed_by' => $actor->id, 'schedule_confirmed_at' => now()]);
            $this->record($locked, $actor, 'AUTHORITY_SCHEDULE_CONFIRMED', $from, $locked->status, null, ['proposal_id' => $proposal->id, 'unit_ids' => $unitIds->all()]);
            return $this->responseRequest($locked);
        });
    }
}
