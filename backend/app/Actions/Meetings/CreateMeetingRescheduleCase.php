<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingRescheduleSupport;
use App\Models\Meeting;
use App\Models\MeetingRescheduleCase;
use App\Models\User;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateMeetingRescheduleCase
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, string $reason): MeetingRescheduleCase
    {
        return DB::transaction(function () use ($actor, $meeting, $reason): MeetingRescheduleCase {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $reason = trim($reason);
            if ($reason === '') throw new HttpException(422, 'RESCHEDULE_REASON_REQUIRED');
            $this->ensureNoActiveCase($locked);
            $governance = $this->resolver($locked);
            $baseline = app(ScheduleVersionService::class)->current($locked);
            $case = MeetingRescheduleCase::create([
                'meeting_id' => $locked->id,
                'baseline_schedule_version_id' => $baseline->id,
                'initiated_by_user_id' => $actor->id,
                'initiated_by_unit_id' => $this->actorUnitId($actor),
                'reason' => $reason,
                'governance_mode' => $governance['mode'],
                'status' => 'OPEN',
            ]);
            $locked->update(['active_reschedule_case_id' => $case->id]);
            $this->recordMeetingAction($locked, $actor, 'RESCHEDULE_CASE_CREATED', $reason, [
                'case_id' => $case->id,
                'baseline_schedule_version_id' => $baseline->id,
                'governance_mode' => $governance['mode'],
            ]);
            $this->notifyFormalUnits($locked, $governance['required_unit_ids'], 'MEETING_RESCHEDULE', 'درخواست تغییر زمان جلسه', 'برای یک جلسه رسمی درخواست تغییر زمان ایجاد شد.', ['case_id' => $case->id]);
            return $case->fresh(['meeting', 'baselineScheduleVersion']);
        });
    }
}
