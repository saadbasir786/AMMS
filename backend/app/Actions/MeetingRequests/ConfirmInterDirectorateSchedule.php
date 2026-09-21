<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmInterDirectorateSchedule
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-schedule.accept'), 403, 'FORBIDDEN');
            $locked = $this->lockInterDirectorateRequest($request);
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::SCHEDULING, 422, 'SCHEDULE_NOT_CONFIRMED');
            $requester = $this->unitFor($locked, 'REQUESTER');
            abort_unless($this->canCoordinateUnit($actor, $requester->id), 403, 'REQUESTER_LEAD_REQUIRED');
            $proposal = $locked->scheduleProposals()->where('status', 'ACCEPTED')->latest('id')->first();
            abort_unless($proposal, 422, 'SCHEDULE_NOT_ACCEPTED');
            abort_unless($locked->requester_participants_confirmed_at && $locked->target_participants_confirmed_at, 422, 'PARTICIPANTS_NOT_CONFIRMED');
            $this->assertTransition($locked, MeetingRequestStatus::SCHEDULE_CONFIRMED, 'SCHEDULE_NOT_CONFIRMED');

            $locked->update([
                'scheduled_date' => $proposal->meeting_date,
                'scheduled_start_time' => $proposal->start_time,
                'scheduled_duration_minutes' => $this->durationMinutes($proposal->start_time, $proposal->end_time),
                'scheduled_location' => $proposal->location,
                'scheduled_venue_type' => $proposal->venue_type ?: 'EXTERNAL_LOCATION',
                'scheduled_venue_id' => $proposal->venue_id,
                'scheduled_venue_snapshot' => $proposal->venue_snapshot,
                'schedule_confirmed_by' => $actor->id,
                'schedule_confirmed_at' => now(),
                'status' => MeetingRequestStatus::SCHEDULE_CONFIRMED->value,
            ]);
            $this->record($locked, $actor, 'SCHEDULE_CONFIRMED', MeetingRequestStatus::SCHEDULING->value, MeetingRequestStatus::SCHEDULE_CONFIRMED->value, null, ['proposal_id' => $proposal->id]);
            return $this->responseRequest($locked);
        });
    }

    private function durationMinutes(string $start, string $end): int
    {
        $startAt = strtotime($start);
        $endAt = strtotime($end);
        abort_unless($startAt !== false && $endAt !== false && $endAt > $startAt, 422, 'INVALID_SCHEDULE_PROPOSAL');
        return (int) (($endAt - $startAt) / 60);
    }
}
