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

class CreateInternalRescheduleProposal
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, MeetingRescheduleCase $case, array $data): MeetingRescheduleProposal
    {
        return DB::transaction(function () use ($actor, $meeting, $case, $data): MeetingRescheduleProposal {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $case = $case->fresh();
            $this->ensureCaseActive($case);

            $governance = $this->resolver($locked);
            abort_unless($governance['mode'] === 'INTERNAL_SINGLE_UNIT', 403, 'UNAUTHORIZED_INTERNAL_RESCHEDULE_PROPOSAL');
            abort_unless((int) $case->meeting_id === (int) $locked->id
                && (int) $locked->active_reschedule_case_id === (int) $case->id, 422, 'RESCHEDULE_CASE_NOT_ACTIVE');
            abort_unless($actor->isActive() && $actor->can('meeting-reschedule.propose'), 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');

            $unitId = $this->actorFormalUnitId($actor, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT']);
            abort_unless($unitId, 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');
            abort_unless((int) $case->initiated_by_unit_id === (int) $unitId, 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');

            abort_if($case->proposals()->whereIn('status', $this->internalProposalStatuses())->exists(), 422, 'RESCHEDULE_PROPOSAL_ALREADY_EXISTS');

            $schedule = app(ScheduleVersionService::class)->normalize($data, $locked);
            $current = app(ScheduleVersionService::class)->current($locked);
            abort_if(app(ScheduleVersionService::class)->sameAs($current, $schedule), 422, 'NO_SCHEDULE_CHANGE');
            app(ScheduleConflictGuard::class)->assertNoConflict($locked, $schedule, $actor);

            $proposal = MeetingRescheduleProposal::create([
                'reschedule_case_id' => $case->id,
                'meeting_id' => $locked->id,
                'proposed_by_user_id' => $actor->id,
                'proposed_by_unit_id' => $unitId,
                'proposal_type' => 'INTERNAL_PROPOSAL',
                ...$schedule,
                'comment' => $data['comment'] ?? null,
                'status' => 'DRAFT',
            ]);

            $this->recordMeetingAction($locked, $actor, 'RESCHEDULE_PROPOSAL_DRAFT_CREATED', $data['comment'] ?? null, [
                'case_id' => $case->id,
                'proposal_id' => $proposal->id,
                'governance_mode' => 'INTERNAL_SINGLE_UNIT',
                'proposed_schedule' => $schedule,
            ]);
            $this->notifyFormalUnits($locked, $governance['required_unit_ids'], 'MEETING_RESCHEDULE', 'پیش‌نویس تغییر زمان جلسه', 'پیش‌نویس زمان پیشنهادی جلسه برای بررسی آماده شد.', [
                'case_id' => $case->id,
                'proposal_id' => $proposal->id,
            ]);

            return $proposal->fresh(['acknowledgements', 'proposedByUnit']);
        });
    }
}
