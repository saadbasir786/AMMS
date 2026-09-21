<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingRescheduleSupport;
use App\Models\Meeting;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingRescheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnInternalRescheduleProposal
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, MeetingRescheduleCase $case, MeetingRescheduleProposal $proposal, string $reason): MeetingRescheduleProposal
    {
        return DB::transaction(function () use ($actor, $meeting, $case, $proposal, $reason): MeetingRescheduleProposal {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $case = $case->fresh();
            $proposal = $proposal->fresh();
            $this->ensureCaseActive($case);
            $governance = $this->resolver($locked);
            abort_unless($governance['mode'] === 'INTERNAL_SINGLE_UNIT'
                && (int) $case->meeting_id === (int) $locked->id
                && (int) $locked->active_reschedule_case_id === (int) $case->id
                && (int) $proposal->meeting_id === (int) $locked->id
                && (int) $proposal->reschedule_case_id === (int) $case->id
                && $proposal->proposal_type === 'INTERNAL_PROPOSAL'
                && in_array($proposal->status, ['SUBMITTED', 'ACTIVE'], true), 422, 'RESCHEDULE_PROPOSAL_NOT_SUBMITTED');

            $unitId = (int) ($governance['required_unit_ids'][0] ?? 0);
            abort_unless($unitId > 0
                && $actor->isActive()
                && $actor->can('meeting-reschedule.acknowledge')
                && $actor->hasScopedRole('DIRECTOR', $unitId)
                && (int) $actor->id !== (int) $proposal->proposed_by_user_id, 403, 'UNAUTHORIZED_RESCHEDULE_DIRECTOR_APPROVAL');
            $reason = trim($reason);
            abort_unless($reason !== '', 422, 'RESCHEDULE_CORRECTION_REASON_REQUIRED');

            $proposal->update(['status' => 'RETURNED_FOR_CORRECTION']);
            $case->update(['status' => 'OPEN']);
            $this->recordMeetingAction($locked, $actor, 'RESCHEDULE_RETURNED_FOR_CORRECTION', $reason, [
                'case_id' => $case->id,
                'proposal_id' => $proposal->id,
                'organizational_unit_id' => $unitId,
            ]);
            $this->notifyUsers([$proposal->proposed_by_user_id], 'MEETING_RESCHEDULE', 'برگشت جهت اصلاح', 'زمان پیشنهادی جلسه برای اصلاح برگشت داده شد.', [
                'case_id' => $case->id,
                'proposal_id' => $proposal->id,
                'meeting_id' => $locked->id,
            ]);

            return $proposal->fresh(['acknowledgements', 'proposedByUnit']);
        });
    }
}
