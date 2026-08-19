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
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreatePeerRescheduleProposal
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, MeetingRescheduleCase $case, array $data): MeetingRescheduleProposal
    {
        return DB::transaction(function () use ($actor, $meeting, $case, $data): MeetingRescheduleProposal {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $this->ensureCaseActive($case->fresh());
            $governance = $this->resolver($locked);
            abort_unless($governance['mode'] === 'PEER_MULTI_UNIT', 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');
            $unitId = $this->actorUnitId($actor);
            abort_unless($unitId && in_array($unitId, $governance['required_unit_ids'], true), 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');
            $schedule = app(ScheduleVersionService::class)->normalize($data);
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
                'proposal_type' => 'PEER_PROPOSAL',
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
