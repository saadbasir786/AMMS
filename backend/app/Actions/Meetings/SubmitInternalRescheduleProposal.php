<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingRescheduleSupport;
use App\Models\Meeting;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingRescheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitInternalRescheduleProposal
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, MeetingRescheduleCase $case, MeetingRescheduleProposal $proposal): MeetingRescheduleProposal
    {
        return DB::transaction(function () use ($actor, $meeting, $case, $proposal): MeetingRescheduleProposal {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $case = $case->fresh();
            $proposal = $proposal->fresh();
            $this->ensureCaseActive($case);
            abort_unless($case->governance_mode === 'INTERNAL_SINGLE_UNIT'
                && (int) $case->meeting_id === (int) $locked->id
                && (int) $locked->active_reschedule_case_id === (int) $case->id
                && (int) $proposal->meeting_id === (int) $locked->id
                && (int) $proposal->reschedule_case_id === (int) $case->id
                && $proposal->proposal_type === 'INTERNAL_PROPOSAL', 422, 'RESCHEDULE_PROPOSAL_NOT_ACTIVE');
            abort_unless(in_array($proposal->status, ['DRAFT', 'RETURNED_FOR_CORRECTION'], true), 422, 'RESCHEDULE_PROPOSAL_NOT_SUBMITTABLE');
            abort_unless($actor->isActive()
                && $actor->id === (int) $proposal->proposed_by_user_id
                && $actor->can('meeting-reschedule.propose')
                && $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $proposal->proposed_by_unit_id), 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');

            abort_if($proposal->acknowledgements()->exists(), 422, 'RESCHEDULE_APPROVAL_ALREADY_RECORDED');
            $proposal->update(['status' => 'SUBMITTED']);
            $case->update(['status' => 'NEGOTIATING']);
            $this->recordMeetingAction($locked, $actor, 'RESCHEDULE_PROPOSAL_SUBMITTED', $proposal->comment, [
                'case_id' => $case->id,
                'proposal_id' => $proposal->id,
                'required_unit_ids' => $this->resolver($locked)['required_unit_ids'],
                'proposed_schedule' => [
                    'meeting_date' => $proposal->meeting_date?->toDateString(),
                    'start_time' => substr((string) $proposal->start_time, 0, 5),
                    'end_time' => substr((string) $proposal->end_time, 0, 5),
                    'location' => $proposal->location,
                ],
            ]);
            $this->notifyFormalUnits($locked, $this->resolver($locked)['required_unit_ids'], 'MEETING_RESCHEDULE', 'تأیید زمان پیشنهادی', 'زمان پیشنهادی جلسه برای تأیید ریاست مربوط ارسال شد.', [
                'case_id' => $case->id,
                'proposal_id' => $proposal->id,
            ]);

            return $proposal->fresh(['acknowledgements', 'proposedByUnit']);
        });
    }
}
