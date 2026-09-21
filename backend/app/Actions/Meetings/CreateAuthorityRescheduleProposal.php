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

class CreateAuthorityRescheduleProposal
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, MeetingRescheduleCase $case, array $data): MeetingRescheduleProposal
    {
        return DB::transaction(function () use ($actor, $meeting, $case, $data): MeetingRescheduleProposal {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $this->ensureCaseActive($case->fresh());
            $governance = $this->resolver($locked);
            abort_unless($governance['mode'] === 'AUTHORITY_CONTROLLED', 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');
            $officeUnitId = $governance['authority_office_unit_id'];
            abort_unless($officeUnitId && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeUnitId), 403, 'UNAUTHORIZED_RESCHEDULE_PROPOSAL');
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
                'proposed_by_unit_id' => $officeUnitId,
                'proposal_type' => 'AUTHORITY_OFFICIAL',
                ...$schedule,
                'comment' => $data['comment'] ?? null,
                'status' => 'ACTIVE',
                'supersedes_proposal_id' => $previous?->id,
            ]);
            $case->update(['status' => 'NEGOTIATING']);
            $this->recordMeetingAction($locked, $actor, 'RESCHEDULE_PROPOSAL_CREATED', $data['comment'] ?? null, [
                'case_id' => $case->id, 'proposal_id' => $proposal->id, 'proposal_type' => 'AUTHORITY_OFFICIAL', 'supersedes_proposal_id' => $previous?->id,
            ]);
            $this->notifyFormalUnits($locked, $governance['required_unit_ids'], 'MEETING_RESCHEDULE', 'پیشنهاد رسمی تغییر زمان جلسه', 'پیشنهاد رسمی زمان‌بندی جلسه برای تأیید ریاست‌ها منتشر شد.', ['case_id' => $case->id, 'proposal_id' => $proposal->id]);
            return $proposal->fresh(['acknowledgements', 'proposedByUnit']);
        });
    }
}
