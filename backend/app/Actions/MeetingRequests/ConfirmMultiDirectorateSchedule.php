<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmMultiDirectorateSchedule
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow03Request($request);
            $this->assertActiveActor($actor);
            $lead = $locked->lead_coordinator_unit_id ?: $this->requesterUnit($locked)->id;
            abort_unless($this->canCoordinateUnit($actor, $lead), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::SCHEDULING->value, 422, 'SCHEDULING_REQUIRED');

            $proposal = $locked->scheduleProposals->firstWhere('status', 'ACCEPTED');
            abort_unless($proposal, 422, 'SCHEDULE_NOT_ACCEPTED_BY_ALL_UNITS');
            $unitIds = $locked->units->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->values();
            $acceptedIds = $proposal->acceptances->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->unique();
            abort_unless($acceptedIds->diff($unitIds)->isEmpty() && $acceptedIds->count() === $unitIds->count(), 422, 'SCHEDULE_NOT_ACCEPTED_BY_ALL_UNITS');

            $locked->update([
                'status' => MeetingRequestStatus::SCHEDULE_CONFIRMED->value,
                'schedule_confirmed_by' => $actor->id,
                'schedule_confirmed_at' => now(),
                'scheduled_date' => $proposal->meeting_date,
                'scheduled_start_time' => $proposal->start_time,
                'scheduled_duration_minutes' => $this->durationMinutes($proposal->start_time, $proposal->end_time),
                'scheduled_location' => $proposal->location,
            ]);
            $this->record($locked, $actor, 'SCHEDULE_CONFIRMED', MeetingRequestStatus::SCHEDULING->value, MeetingRequestStatus::SCHEDULE_CONFIRMED->value, null, ['proposal_id' => $proposal->id, 'unit_ids' => $unitIds->all()]);

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
