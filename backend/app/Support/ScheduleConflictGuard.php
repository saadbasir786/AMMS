<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ScheduleConflictGuard
{
    public function employeeIds(Meeting $meeting): array
    {
        $ids = [(int) $meeting->chairperson_employee_id];
        if ($meeting->authorityContext?->authority_employee_id) $ids[] = (int) $meeting->authorityContext->authority_employee_id;
        $ids = [...$ids, ...$meeting->participants()->pluck('employee_id')->map(fn ($id): int => (int) $id)->all()];
        $ids = array_values(array_unique(array_filter($ids)));
        sort($ids);
        return $ids;
    }

    public function assertNoConflict(Meeting $meeting, array $schedule, ?User $actor = null): void
    {
        $date = $schedule['meeting_date'];
        $start = substr($schedule['start_time'], 0, 5);
        $end = substr($schedule['end_time'], 0, 5);
        $candidates = Meeting::query()
            ->where('status', 'SCHEDULED')
            ->where('id', '<>', $meeting->id)
            ->whereDate('meeting_date', $date)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->with(['participants', 'authorityContext'])
            ->get();
        $targetEmployees = $this->employeeIds($meeting);

        foreach ($candidates as $other) {
            $otherEmployees = $this->employeeIds($other);
            $shared = array_values(array_intersect($targetEmployees, $otherEmployees));
            if ($shared === []) continue;
            $authorityEmployee = (int) ($meeting->authorityContext?->authority_employee_id ?? 0);
            $otherAuthorityEmployee = (int) ($other->authorityContext?->authority_employee_id ?? 0);
            if ($authorityEmployee && in_array($authorityEmployee, $shared, true) || $otherAuthorityEmployee && in_array($otherAuthorityEmployee, $shared, true)) {
                throw new HttpException(422, 'HIGH_AUTHORITY_SCHEDULE_CONFLICT');
            }
            if (in_array((int) $meeting->chairperson_employee_id, $shared, true) || in_array((int) $other->chairperson_employee_id, $shared, true)) {
                throw new HttpException(422, 'CHAIRPERSON_SCHEDULE_CONFLICT');
            }
            throw new HttpException(422, 'PARTICIPANT_SCHEDULE_CONFLICT');
        }
    }
}
