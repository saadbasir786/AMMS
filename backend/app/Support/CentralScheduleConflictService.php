<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The single authoritative schedule invariant used by every activation path.
 *
 * The date lock is deliberately separate from meeting rows. It closes the
 * empty-set race where two new meetings are checked before either is visible.
 */
class CentralScheduleConflictService
{
    public const ACTIVE_STATUSES = ['SCHEDULED', 'IN_PROGRESS'];

    public function evaluateMeeting(Meeting $meeting, array $schedule, bool $lockDate = false): array
    {
        if ($lockDate) $this->lockDate((string) $schedule['meeting_date']);

        $meeting->load(['participants', 'units.unit.parent', 'organizingUnit.parent', 'authorityContext']);
        if (($schedule['venue_type'] ?? $meeting->venue_type) === ScheduleVenueService::INTERNAL) {
            app(ScheduleVenueService::class)->normalize([
                'venue_type' => ScheduleVenueService::INTERNAL,
                'venue_id' => $schedule['venue_id'] ?? $meeting->venue_id,
                'location' => $meeting->location,
            ], $meeting);
        }
        $candidate = [
            'meeting_id' => (int) $meeting->id,
            'employee_ids' => $this->meetingEmployeeIds($meeting),
            'chairperson_id' => (int) $meeting->chairperson_employee_id,
            'authority_id' => (int) ($meeting->authorityContext?->authority_employee_id ?? 0),
            'formal_unit_ids' => $this->meetingFormalUnitIds($meeting),
            'venue_type' => (string) ($schedule['venue_type'] ?? $meeting->venue_type ?? ScheduleVenueService::EXTERNAL),
            'venue_id' => isset($schedule['venue_id']) ? (int) $schedule['venue_id'] : (int) ($meeting->venue_id ?? 0),
        ];

        return $this->evaluateCandidate($candidate, $schedule, (int) $meeting->id);
    }

    public function evaluateMeetingEmployeeIds(Meeting $meeting, array $schedule, array $employeeIds, bool $lockDate = false): array
    {
        if ($lockDate) $this->lockDate((string) $schedule['meeting_date']);
        $meeting->load(['units.unit.parent', 'organizingUnit.parent', 'authorityContext']);
        $candidate = [
            'meeting_id' => (int) $meeting->id,
            'employee_ids' => array_values(array_unique(array_filter(array_map('intval', $employeeIds)))),
            'chairperson_id' => (int) $meeting->chairperson_employee_id,
            'authority_id' => (int) ($meeting->authorityContext?->authority_employee_id ?? 0),
            'formal_unit_ids' => $this->meetingFormalUnitIds($meeting),
            'venue_type' => (string) ($schedule['venue_type'] ?? $meeting->venue_type ?? ScheduleVenueService::EXTERNAL),
            'venue_id' => isset($schedule['venue_id']) ? (int) $schedule['venue_id'] : (int) ($meeting->venue_id ?? 0),
        ];

        return $this->evaluateCandidate($candidate, $schedule, (int) $meeting->id);
    }

    public function evaluateRequest(MeetingRequest $request, array $schedule, int $chairpersonId, array $participantIds = [], ?int $authorityId = null, bool $lockDate = false): array
    {
        if ($lockDate) $this->lockDate((string) $schedule['meeting_date']);

        $request->loadMissing(['units.unit.parent', 'requestingUnit.parent']);
        $candidate = [
            'meeting_id' => 0,
            'employee_ids' => array_values(array_unique(array_filter(array_map('intval', [$chairpersonId, $authorityId, ...$participantIds])))),
            'chairperson_id' => $chairpersonId,
            'authority_id' => (int) ($authorityId ?? 0),
            'formal_unit_ids' => $this->requestFormalUnitIds($request),
            'venue_type' => (string) ($schedule['venue_type'] ?? ScheduleVenueService::EXTERNAL),
            'venue_id' => (int) ($schedule['venue_id'] ?? 0),
        ];

        return $this->evaluateCandidate($candidate, $schedule, null);
    }

    public function assertNoBlocking(array $result): void
    {
        $blocking = $result['blocking_conflicts'] ?? [];
        if ($blocking === []) return;

        $type = (string) ($blocking[0]['type'] ?? 'SCHEDULE_CONFLICT');
        throw new HttpException(422, $type);
    }

    public function assertNoConflict(Meeting $meeting, array $schedule, ?\App\Models\User $actor = null): void
    {
        $this->assertNoBlocking($this->evaluateMeeting($meeting, $schedule, true));
    }

    public function lockDate(string $date): void
    {
        DB::table('schedule_conflict_locks')->insertOrIgnore([
            'schedule_date' => $date,
            'created_at' => now(),
        ]);
        DB::table('schedule_conflict_locks')->where('schedule_date', $date)->lockForUpdate()->first();
    }

    public function employeeIds(Meeting $meeting): array
    {
        $meeting->load(['participants', 'authorityContext']);
        return $this->meetingEmployeeIds($meeting);
    }

    private function evaluateCandidate(array $candidate, array $schedule, ?int $excludedMeetingId): array
    {
        $date = (string) $schedule['meeting_date'];
        $start = substr((string) $schedule['start_time'], 0, 5);
        $end = substr((string) $schedule['end_time'], 0, 5);
        $others = Meeting::query()
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->when($excludedMeetingId, fn ($query) => $query->where('id', '<>', $excludedMeetingId))
            ->whereDate('meeting_date', $date)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->with(['participants', 'units.unit.parent', 'organizingUnit.parent', 'authorityContext'])
            ->get();

        $blocking = [];
        $warnings = [];
        foreach ($others as $other) {
            $otherCandidate = [
                'meeting_id' => (int) $other->id,
                'employee_ids' => $this->meetingEmployeeIds($other),
                'chairperson_id' => (int) $other->chairperson_employee_id,
                'authority_id' => (int) ($other->authorityContext?->authority_employee_id ?? 0),
                'formal_unit_ids' => $this->meetingFormalUnitIds($other),
                'venue_type' => (string) ($other->venue_type ?? ScheduleVenueService::EXTERNAL),
                'venue_id' => (int) ($other->venue_id ?? 0),
            ];
            $sharedEmployees = array_values(array_intersect($candidate['employee_ids'], $otherCandidate['employee_ids']));

            if ($sharedEmployees !== []) {
                $type = in_array((int) $candidate['authority_id'], $sharedEmployees, true)
                    || in_array((int) $otherCandidate['authority_id'], $sharedEmployees, true)
                    ? 'HIGH_AUTHORITY_SCHEDULE_CONFLICT'
                    : (in_array((int) $candidate['chairperson_id'], $sharedEmployees, true)
                        || in_array((int) $otherCandidate['chairperson_id'], $sharedEmployees, true)
                        ? 'CHAIRPERSON_SCHEDULE_CONFLICT'
                        : 'PARTICIPANT_SCHEDULE_CONFLICT');
                $blocking[] = $this->conflictDetail($type, $other, $sharedEmployees, $start, $end);
                continue;
            }

            if ($candidate['venue_type'] === ScheduleVenueService::INTERNAL
                && $otherCandidate['venue_type'] === ScheduleVenueService::INTERNAL
                && $candidate['venue_id'] > 0
                && $candidate['venue_id'] === $otherCandidate['venue_id']) {
                $blocking[] = $this->conflictDetail('INTERNAL_VENUE_SCHEDULE_CONFLICT', $other, [], $start, $end);
                continue;
            }

            $sharedUnits = array_values(array_intersect($candidate['formal_unit_ids'], $otherCandidate['formal_unit_ids']));
            if ($sharedUnits !== []) {
                $warnings[] = [
                    'type' => 'DIRECTORATE_SCHEDULE_OVERLAP',
                    'organizational_unit_ids' => $sharedUnits,
                    'date' => $date,
                    'start_time' => $start,
                    'end_time' => $end,
                ];
            }
        }

        return ['blocking_conflicts' => $blocking, 'warnings' => $warnings];
    }

    private function meetingEmployeeIds(Meeting $meeting): array
    {
        $ids = [(int) $meeting->chairperson_employee_id, (int) ($meeting->authorityContext?->authority_employee_id ?? 0)];
        $ids = [...$ids, ...$meeting->participants->pluck('employee_id')->map(fn ($id): int => (int) $id)->all()];
        return array_values(array_unique(array_filter($ids)));
    }

    private function meetingFormalUnitIds(Meeting $meeting): array
    {
        $units = collect([$meeting->organizingUnit])->filter();
        $units = $units->concat($meeting->units->map(fn ($unit) => $unit->unit)->filter());
        return $this->formalUnitIds($units);
    }

    private function requestFormalUnitIds(MeetingRequest $request): array
    {
        $units = $request->units->map(fn ($unit) => $unit->unit)->filter();
        if ($units->isEmpty() && $request->requestingUnit) $units = collect([$request->requestingUnit]);
        return $this->formalUnitIds($units);
    }

    private function formalUnitIds(Collection $units): array
    {
        return $units->map(fn ($unit) => $unit->formalGovernanceUnit()?->id ?? ($unit->isFormalGovernanceUnit() ? $unit->id : null))
            ->filter()->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }

    private function conflictDetail(string $type, Meeting $other, array $employeeIds, string $start, string $end): array
    {
        return [
            'type' => $type,
            'date' => $other->meeting_date?->toDateString(),
            'start_time' => substr((string) $other->start_time, 0, 5),
            'end_time' => substr((string) $other->end_time, 0, 5),
        ];
    }
}
