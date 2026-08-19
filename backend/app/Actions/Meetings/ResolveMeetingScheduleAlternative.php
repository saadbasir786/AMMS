<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingRescheduleSupport;
use App\Models\Meeting;
use App\Models\MeetingRescheduleAlternativeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResolveMeetingScheduleAlternative
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, MeetingRescheduleAlternativeRequest $alternative): MeetingRescheduleAlternativeRequest
    {
        return DB::transaction(function () use ($actor, $meeting, $alternative): MeetingRescheduleAlternativeRequest {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $alternative = $alternative->fresh(['rescheduleCase', 'proposal']);
            $case = $alternative->rescheduleCase;
            $governance = $this->resolver($locked);
            abort_unless($governance['mode'] === 'AUTHORITY_CONTROLLED', 403, 'UNAUTHORIZED_RESCHEDULE_CONFIRMATION');
            abort_unless($governance['authority_office_unit_id'] && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']), 403, 'UNAUTHORIZED_RESCHEDULE_CONFIRMATION');
            $this->ensureCaseActive($case);
            abort_unless((int) $alternative->proposal->meeting_id === (int) $locked->id && $alternative->status === 'OPEN', 422, 'ALTERNATIVE_NOT_OPEN');
            $alternative->update(['status' => 'RESOLVED', 'resolved_at' => now(), 'resolved_by_user_id' => $actor->id]);
            $this->recordMeetingAction($locked, $actor, 'RESCHEDULE_ALTERNATIVE_RESOLVED', null, [
                'case_id' => $case->id, 'proposal_id' => $alternative->proposal_id, 'alternative_request_id' => $alternative->id,
            ]);
            return $alternative->fresh(['unit', 'resolvedBy']);
        });
    }
}
