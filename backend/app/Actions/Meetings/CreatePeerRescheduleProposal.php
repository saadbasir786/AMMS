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

class CreatePeerRescheduleProposal
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, MeetingRescheduleCase $case, array $data): MeetingRescheduleProposal
    {
        if ($this->resolver($meeting)['mode'] === 'INTERNAL_SINGLE_UNIT') {
            return app(CreateInternalRescheduleProposal::class)($actor, $meeting, $case, $data);
        }

        return DB::transaction(function () use ($actor, $meeting, $case, $data): MeetingRescheduleProposal {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $this->ensureCaseActive($case->fresh());
            $governance = $this->resolver($locked);
            abort_unless(in_array($governance['mode'], ['INTERNAL_SINGLE_UNIT', 'PEER_MULTI_UNIT'], true), 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');
            abort_unless($actor->isActive() && $actor->can('meeting-reschedule.propose'), 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');
            $unitId = $this->actorFormalUnitId($actor, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT']);
            abort_unless($unitId, 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');
            $schedule = app(ScheduleVersionService::class)->normalize($data, $locked);
            $current = app(ScheduleVersionService::class)->current($locked);
            abort_if(app(ScheduleVersionService::class)->sameAs($current, $schedule), 422, 'NO_SCHEDULE_CHANGE');
            app(ScheduleConflictGuard::class)->assertNoConflict($locked, $schedule, $actor);
            $previous = $case->proposals()->where('status', 'ACTIVE')->latest('id')->first();
            if ($previous) $previous->update(['status' => 'SUPERSEDED']);
            $proposal = MeetingRescheduleProposal::create([
                'reschedule_case_id' => $case->id,
                'meeting_id' => $locked->id,
                'proposed_by_user_id' => $actor->id,
                'proposed_by_unit_id' => $unitId,
                'proposal_type' => $governance['mode'] === 'INTERNAL_SINGLE_UNIT' ? 'INTERNAL_PROPOSAL' : 'PEER_PROPOSAL',
                ...$schedule,
                'comment' => $data['comment'] ?? null,
                'status' => 'ACTIVE',
                'supersedes_proposal_id' => $previous?->id,
            ]);
            $case->update(['status' => 'NEGOTIATING']);
            $this->recordMeetingAction($locked, $actor, $previous ? 'RESCHEDULE_COUNTER_PROPOSAL_CREATED' : 'RESCHEDULE_PROPOSAL_CREATED', $data['comment'] ?? null, [
                'case_id' => $case->id, 'proposal_id' => $proposal->id, 'supersedes_proposal_id' => $previous?->id,
            ]);
            $this->notifyFormalUnits($locked, $governance['required_unit_ids'], 'MEETING_RESCHEDULE', 'پیشنهاد جدید تغییر زمان جلسه', 'یک پیشنهاد جدید برای تغییر زمان جلسه ایجاد شد و نیاز به تأیید ریاست‌ها دارد.', ['case_id' => $case->id, 'proposal_id' => $proposal->id]);
            return $proposal->fresh(['acknowledgements', 'proposedByUnit']);
        });
    }
}
