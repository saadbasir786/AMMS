<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmAuthorityInitiatedSchedule
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            $this->assertActiveActor($actor);
            $officeId = (int) ($request->authorityContext?->authority_office_unit_id ?? 0);
            abort_unless($officeId && $actor->can('authority-schedule.confirm') && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeId), 403, 'UNAUTHORIZED_AUTHORITY_SCHEDULER');
            abort_unless($request->status === MeetingRequestStatus::AUTHORITY_SCHEDULING->value, 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            $proposal = $request->scheduleProposals()->where('status', 'PROPOSED')->latest('id')->lockForUpdate()->first();
            abort_unless($proposal, 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            abort_unless((string) $proposal->end_time > (string) $proposal->start_time, 422, 'INVALID_SCHEDULE_TIME');

            $unitIds = $this->flow06Units($request)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
            $ackIds = $request->authorityScheduleUnitAcknowledgements()
                ->where('meeting_schedule_proposal_id', $proposal->id)
                ->whereIn('organizational_unit_id', $unitIds)
                ->pluck('organizational_unit_id')
                ->map(fn ($id) => (int) $id)
                ->unique()->sort()->values();
            abort_unless($ackIds->count() === $unitIds->count() && $ackIds->diff($unitIds)->isEmpty(), 422, 'AUTHORITY_SCHEDULE_NOT_ACKNOWLEDGED_BY_ALL');

            $proposal->update(['status' => 'ACCEPTED']);
            $from = $this->transition06($request, MeetingRequestStatus::SCHEDULE_CONFIRMED);
            $request->update([
                'scheduled_date' => $proposal->meeting_date,
                'scheduled_start_time' => $proposal->start_time,
                'scheduled_duration_minutes' => $this->durationMinutes($proposal->start_time, $proposal->end_time),
                'scheduled_location' => $proposal->location,
                'schedule_confirmed_by' => $actor->id,
                'schedule_confirmed_at' => now(),
            ]);
            $this->record06($request, $actor, 'AUTHORITY_SCHEDULE_CONFIRMED', $from, MeetingRequestStatus::SCHEDULE_CONFIRMED->value, null, ['proposal_id' => $proposal->id]);
            return $this->response06($request);
        });
    }

    private function durationMinutes(string $start, string $end): int
    {
        $startAt = strtotime($start);
        $endAt = strtotime($end);
        abort_unless($startAt !== false && $endAt !== false && $endAt > $startAt, 422, 'INVALID_SCHEDULE_TIME');
        return (int) (($endAt - $startAt) / 60);
    }
}
