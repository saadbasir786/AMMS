<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ScheduleConflictGuard
{
    public function __construct(private CentralScheduleConflictService $central) {}

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
        $this->central->assertNoConflict($meeting, $schedule, $actor);
    }

    public function assertEmployeeIdsNoConflict(Meeting $meeting, array $schedule, array $employeeIds): void
    {
        $this->central->assertNoBlocking($this->central->evaluateMeetingEmployeeIds($meeting, $schedule, $employeeIds, true));
    }
}
