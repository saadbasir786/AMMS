<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingRescheduleSupport;
use App\Models\Meeting;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingRescheduleProposal;
use App\Models\User;
use App\Support\ScheduleConflictGuard;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Facades\DB;

class UpdateInternalRescheduleProposal
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, MeetingRescheduleCase $case, MeetingRescheduleProposal $proposal, array $data): MeetingRescheduleProposal
    {
        return DB::transaction(function () use ($actor, $meeting, $case, $proposal, $data): MeetingRescheduleProposal {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $case = $case->fresh();
            $proposal = $proposal->fresh();
            $this->ensureCaseActive($case);
            $this->assertInternalProposal($locked, $case, $proposal);
            abort_unless(in_array($proposal->status, ['DRAFT', 'RETURNED_FOR_CORRECTION'], true), 422, 'RESCHEDULE_PROPOSAL_NOT_EDITABLE');
            abort_unless($actor->isActive()
                && $actor->id === (int) $proposal->proposed_by_user_id
                && $actor->can('meeting-reschedule.propose')
                && $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $proposal->proposed_by_unit_id), 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');

            $schedule = app(ScheduleVersionService::class)->normalize($data, $locked);
            $current = app(ScheduleVersionService::class)->current($locked);
            abort_if(app(ScheduleVersionService::class)->sameAs($current, $schedule), 422, 'NO_SCHEDULE_CHANGE');
            app(ScheduleConflictGuard::class)->assertNoConflict($locked, $schedule, $actor);

            $proposal->update([
                ...$schedule,
                'comment' => $data['comment'] ?? null,
            ]);
            $this->recordMeetingAction($locked, $actor, 'RESCHEDULE_PROPOSAL_DRAFT_UPDATED', $data['comment'] ?? null, [
                'case_id' => $case->id,
                'proposal_id' => $proposal->id,
                'proposed_schedule' => $schedule,
            ]);

            return $proposal->fresh(['acknowledgements', 'proposedByUnit']);
        });
    }

    private function assertInternalProposal(Meeting $meeting, MeetingRescheduleCase $case, MeetingRescheduleProposal $proposal): void
    {
        abort_unless($case->governance_mode === 'INTERNAL_SINGLE_UNIT'
            && (int) $case->meeting_id === (int) $meeting->id
            && (int) $proposal->meeting_id === (int) $meeting->id
            && (int) $proposal->reschedule_case_id === (int) $case->id
            && $proposal->proposal_type === 'INTERNAL_PROPOSAL', 422, 'RESCHEDULE_PROPOSAL_NOT_ACTIVE');
    }
}
