<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingRescheduleSupport;
use App\Models\Meeting;
use App\Models\MeetingRescheduleAcknowledgement;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingRescheduleProposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcknowledgeMeetingRescheduleProposal
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, MeetingRescheduleCase $case, MeetingRescheduleProposal $proposal): MeetingRescheduleAcknowledgement
    {
        return DB::transaction(function () use ($actor, $meeting, $case, $proposal): MeetingRescheduleAcknowledgement {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $case = $case->fresh();
            $proposal = $proposal->fresh();
            $this->ensureCaseActive($case);
            $governance = $this->resolver($locked);
            $expectedStatuses = $governance['mode'] === 'INTERNAL_SINGLE_UNIT' ? ['SUBMITTED', 'ACTIVE'] : ['ACTIVE'];
            abort_unless((int) $proposal->meeting_id === (int) $locked->id
                && (int) $proposal->reschedule_case_id === (int) $case->id
                && in_array($proposal->status, $expectedStatuses, true), 422, 'RESCHEDULE_PROPOSAL_NOT_ACTIVE');

            if ($governance['mode'] === 'INTERNAL_SINGLE_UNIT') {
                abort_unless((int) $proposal->meeting_id === (int) $locked->id
                    && $proposal->proposal_type === 'INTERNAL_PROPOSAL'
                    && in_array($proposal->status, ['SUBMITTED', 'ACTIVE'], true), 422, 'RESCHEDULE_PROPOSAL_NOT_SUBMITTED');
                $unitId = (int) ($governance['required_unit_ids'][0] ?? 0);
                abort_unless($unitId > 0
                    && $actor->isActive()
                    && $actor->can('meeting-reschedule.acknowledge')
                    && $actor->hasScopedRole('DIRECTOR', $unitId)
                    && (int) $actor->id !== (int) $proposal->proposed_by_user_id, 403, 'UNAUTHORIZED_RESCHEDULE_DIRECTOR_APPROVAL');

                $ack = MeetingRescheduleAcknowledgement::query()->where([
                    'proposal_id' => $proposal->id,
                    'organizational_unit_id' => $unitId,
                ])->first();
                $previousAcknowledgementUserId = $ack?->acknowledged_by_user_id;
                if ($ack) {
                    $ack->update([
                        'acknowledged_by_user_id' => $actor->id,
                        'acknowledged_at' => now(),
                    ]);
                } else {
                    $ack = MeetingRescheduleAcknowledgement::create([
                        'proposal_id' => $proposal->id,
                        'organizational_unit_id' => $unitId,
                        'acknowledged_by_user_id' => $actor->id,
                        'acknowledged_at' => now(),
                    ]);
                }
                $proposal->update(['status' => 'APPROVED']);
                $case->update(['status' => 'READY_TO_CONFIRM']);
                $this->recordMeetingAction($locked, $actor, 'RESCHEDULE_DIRECTOR_APPROVED', null, [
                    'case_id' => $case->id,
                    'proposal_id' => $proposal->id,
                    'organizational_unit_id' => $unitId,
                    'approval_role' => 'DIRECTOR',
                    'previous_acknowledgement_user_id' => $previousAcknowledgementUserId,
                ]);
                $this->notifyUsers([$proposal->proposed_by_user_id, $locked->coordinator_id], 'MEETING_RESCHEDULE', 'تأیید زمان پیشنهادی', 'زمان پیشنهادی جلسه توسط ریاست مربوط تأیید شد و برای نهایی‌سازی آماده است.', [
                    'meeting_id' => $locked->id,
                    'case_id' => $case->id,
                    'proposal_id' => $proposal->id,
                ]);

                return $ack->fresh(['unit', 'acknowledgedBy']);
            }

            abort_unless($actor->isActive() && $actor->can('meeting-reschedule.acknowledge'), 403, 'UNAUTHORIZED_RESCHEDULE_ACKNOWLEDGEMENT');
            $unitId = $this->actorFormalUnitId($actor, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT', 'DIRECTOR']);
            abort_unless($unitId, 403, 'UNAUTHORIZED_RESCHEDULE_ACKNOWLEDGEMENT');
            $ack = MeetingRescheduleAcknowledgement::firstOrCreate([
                'proposal_id' => $proposal->id,
                'organizational_unit_id' => $unitId,
            ], [
                'acknowledged_by_user_id' => $actor->id,
                'acknowledged_at' => now(),
            ]);
            $required = collect($governance['required_unit_ids']);
            $acknowledged = $proposal->acknowledgements()->pluck('organizational_unit_id')->map(fn ($id): int => (int) $id);
            if ($required->diff($acknowledged)->isEmpty()) {
                $case->update(['status' => 'READY_TO_CONFIRM']);
                $this->recordMeetingAction($locked, $actor, 'RESCHEDULE_READY_TO_CONFIRM', null, [
                    'case_id' => $case->id, 'proposal_id' => $proposal->id, 'required_unit_ids' => $required->all(),
                ]);
                $this->notifyUsers([$locked->coordinator_id, ...($governance['authority_controlled'] ? $this->authoritySchedulerIds($governance['authority_office_unit_id']) : [])], 'MEETING_RESCHEDULE', 'تغییر زمان آمادهٔ تأیید', 'تمام ریاست‌های لازم زمان پیشنهادی را تأیید کردند.', ['meeting_id' => $locked->id, 'case_id' => $case->id, 'proposal_id' => $proposal->id]);
            } else {
                $this->recordMeetingAction($locked, $actor, 'RESCHEDULE_UNIT_ACKNOWLEDGED', null, [
                    'case_id' => $case->id, 'proposal_id' => $proposal->id, 'organizational_unit_id' => $unitId,
                ]);
            }
            return $ack->fresh(['unit', 'acknowledgedBy']);
        });
    }
}
