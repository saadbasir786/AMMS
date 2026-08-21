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
            $locked = $this->lockFlow02Request($request);
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::SCHEDULING, 422, 'SCHEDULE_NOT_CONFIRMED');
            $requester = $this->unitFor($locked, 'REQUESTER');
            $target = $this->unitFor($locked, 'TARGET');
            abort_unless($actor->id === $locked->lead_coordinator_user_id || $this->canCoordinateUnit($actor, $requester->id) || $this->canCoordinateUnit($actor, $target->id), 403, 'FORBIDDEN');
            $proposal = $locked->scheduleProposals()->where('status', 'ACCEPTED')->latest('id')->first();
            abort_unless($proposal, 422, 'SCHEDULE_NOT_ACCEPTED');
            $this->assertTransition($locked, MeetingRequestStatus::SCHEDULE_CONFIRMED, 'SCHEDULE_NOT_CONFIRMED');

            $locked->update([
                'scheduled_date' => $proposal->meeting_date,
                'scheduled_start_time' => $proposal->start_time,
                'scheduled_duration_minutes' => $this->durationMinutes($proposal->start_time, $proposal->end_time),
                'scheduled_location' => $proposal->location,
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
