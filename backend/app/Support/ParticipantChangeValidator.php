<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\MeetingParticipantChangeItem;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ParticipantChangeValidator
{
    public function __construct(
        private readonly ParticipantGovernanceResolver $governance,
        private readonly ScheduleConflictGuard $conflictGuard,
    ) {
    }

    public function assertMeetingEditable(Meeting $meeting): void
    {
        if ($meeting->status !== 'SCHEDULED') {
            throw new HttpException(422, in_array($meeting->status, ['IN_PROGRESS', 'HELD', 'COMPLETED'], true) ? 'MEETING_ALREADY_STARTED' : 'MEETING_NOT_PARTICIPANT_EDITABLE');
        }
        $schedule = $meeting->currentScheduleVersion;
        $date = $schedule?->meeting_date?->format('Y-m-d') ?? $meeting->meeting_date?->format('Y-m-d');
        $start = $schedule?->start_time ?? $meeting->start_time;
        if (!$date || !$start) throw new HttpException(422, 'MEETING_NOT_PARTICIPANT_EDITABLE');
        $startsAt = CarbonImmutable::createFromFormat('Y-m-d H:i', $date.' '.substr((string) $start, 0, 5), 'Asia/Kabul');
        if (CarbonImmutable::now('Asia/Kabul')->greaterThanOrEqualTo($startsAt)) throw new HttpException(422, 'MEETING_ALREADY_STARTED');
    }

    public function assertReason(?string $reason): string
    {
        $reason = trim((string) $reason);
        if ($reason === '') throw new HttpException(422, 'PARTICIPANT_CHANGE_REASON_REQUIRED');
        return $reason;
    }

    public function assertItem(MeetingParticipantChangeCase $case, string $operation, ?int $oldEmployeeId, ?int $newEmployeeId): void
    {
        $operation = strtoupper($operation);
        if (!in_array($operation, ['ADD', 'REMOVE', 'REPLACE'], true)) throw new HttpException(422, 'INVALID_PARTICIPANT_CHANGE_OPERATION');
        if ($operation === 'ADD' && (!$newEmployeeId || $oldEmployeeId)) throw new HttpException(422, 'INVALID_PARTICIPANT_CHANGE_ITEM');
        if ($operation === 'REMOVE' && (!$oldEmployeeId || $newEmployeeId)) throw new HttpException(422, 'INVALID_PARTICIPANT_CHANGE_ITEM');
        if ($operation === 'REPLACE' && (!$oldEmployeeId || !$newEmployeeId)) throw new HttpException(422, 'INVALID_PARTICIPANT_CHANGE_ITEM');
        if ($operation === 'REPLACE' && (int) $oldEmployeeId === (int) $newEmployeeId) throw new HttpException(422, 'NO_PARTICIPANT_CHANGE');
        $this->assertCaseOwnerForEmployees($case, $oldEmployeeId, $newEmployeeId);
        $this->assertCurrentPresence($case->meeting, $operation, $oldEmployeeId, $newEmployeeId);
        $authorityId = (int) ($case->meeting->authorityContext?->authority_employee_id ?? 0);
        if (in_array($operation, ['REMOVE', 'REPLACE'], true) && $authorityId > 0 && (int) $oldEmployeeId === $authorityId && $case->meeting->currentParticipantVersion?->members->firstWhere('employee_id', $authorityId)) throw new HttpException(422, 'HIGH_AUTHORITY_PARTICIPANT_IMMUTABLE');
        if (in_array($operation, ['REMOVE', 'REPLACE'], true) && (int) $oldEmployeeId === (int) $case->meeting->chairperson_employee_id) throw new HttpException(422, 'CHAIRPERSON_PARTICIPANT_REQUIRED');
        $this->assertEmployeeValidity($case, $operation, $oldEmployeeId, $newEmployeeId);
    }

    public function assertCaseReady(MeetingParticipantChangeCase $case): void
    {
        $this->assertMeetingEditable($case->meeting);
        if ($case->baseline_participant_version_id !== $case->meeting->current_participant_version_id) throw new HttpException(422, 'PARTICIPANT_VERSION_CHANGED');
        if ((int) $case->baseline_schedule_version_id !== (int) $case->meeting->current_schedule_version_id) throw new HttpException(422, 'MEETING_SCHEDULE_CHANGED_REVIEW_REQUIRED');
        if ($case->items()->count() === 0) throw new HttpException(422, 'PARTICIPANT_CHANGE_NOT_READY');
    }

    public function applyDelta(MeetingParticipantChangeCase $case, array $members): array
    {
        $byEmployee = collect($members)->keyBy(fn (array $member): int => (int) $member['employee_id']);
        foreach ($case->items as $item) {
            if ($item->operation === 'ADD') {
                $employee = Employee::findOrFail($item->new_employee_id);
                $byEmployee->put($employee->id, $this->memberFromEmployee($employee, $case, $item->participant_role ?: 'PARTICIPATING'));
            } elseif ($item->operation === 'REMOVE') {
                $byEmployee->forget((int) $item->old_employee_id);
            } else {
                $byEmployee->forget((int) $item->old_employee_id);
                $employee = Employee::findOrFail($item->new_employee_id);
                $byEmployee->put($employee->id, $this->memberFromEmployee($employee, $case, $item->participant_role ?: 'PARTICIPATING'));
            }
        }

        $this->assertMinimums($case->meeting, $byEmployee->values()->all());
        $this->assertProtectedParticipants($case->meeting, $byEmployee->values()->all());
        return $byEmployee->values()->sortBy('employee_id')->values()->all();
    }

    public function assertFinalConflicts(Meeting $meeting, array $members, array $candidateEmployeeIds = []): void
    {
        $candidateEmployeeIds = array_values(array_unique(array_filter(array_map('intval', $candidateEmployeeIds))));
        if ($candidateEmployeeIds === []) return;
        $this->conflictGuard->assertEmployeeIdsNoConflict($meeting, [
            'meeting_date' => $meeting->currentScheduleVersion?->meeting_date?->format('Y-m-d') ?? $meeting->meeting_date->format('Y-m-d'),
            'start_time' => $meeting->currentScheduleVersion?->start_time ?? $meeting->start_time,
            'end_time' => $meeting->currentScheduleVersion?->end_time ?? $meeting->end_time,
        ], $candidateEmployeeIds);
    }

    private function assertCaseOwnerForEmployees(MeetingParticipantChangeCase $case, ?int $oldEmployeeId, ?int $newEmployeeId): void
    {
        $meeting = $case->meeting;
        foreach ([$oldEmployeeId, $newEmployeeId] as $employeeId) {
            if (!$employeeId) continue;
            $member = $meeting->currentParticipantVersion?->members->firstWhere('employee_id', $employeeId);
            if (!$member) continue;
            if ($member->owner_type !== $case->owner_type || (int) ($member->owner_unit_id ?? 0) !== (int) ($case->owner_unit_id ?? 0)) {
                throw new HttpException(403, 'UNAUTHORIZED_PARTICIPANT_DOMAIN');
            }
        }
    }

    private function assertCurrentPresence(Meeting $meeting, string $operation, ?int $oldEmployeeId, ?int $newEmployeeId): void
    {
        $ids = $meeting->participants()->pluck('employee_id')->map(fn ($id): int => (int) $id)->all();
        if (in_array($operation, ['REMOVE', 'REPLACE'], true) && !in_array((int) $oldEmployeeId, $ids, true)) throw new HttpException(422, 'PARTICIPANT_NOT_PRESENT');
        if (in_array($operation, ['ADD', 'REPLACE'], true) && in_array((int) $newEmployeeId, $ids, true)) throw new HttpException(422, 'PARTICIPANT_ALREADY_PRESENT');
    }

    private function assertEmployeeValidity(MeetingParticipantChangeCase $case, string $operation, ?int $oldEmployeeId, ?int $newEmployeeId): void
    {
        foreach ([$newEmployeeId] as $employeeId) {
            if (!$employeeId) continue;
            $employee = Employee::with('unit')->find($employeeId);
            if (!$employee) throw new HttpException(422, 'EMPLOYEE_OUTSIDE_PARTICIPANT_DOMAIN');
            if ($employee->status !== 'ACTIVE') throw new HttpException(422, 'EMPLOYEE_INACTIVE');
            if ($case->owner_type === 'UNIT') {
                $ownerUnit = $case->ownerUnit;
                if (!$ownerUnit || !$ownerUnit->isWithinScope((int) $employee->organizational_unit_id)) throw new HttpException(422, 'EMPLOYEE_OUTSIDE_PARTICIPANT_DOMAIN');
            } else {
                $officeId = (int) ($case->meeting->authorityContext?->authority_office_unit_id ?? 0);
                if (!$officeId || !$employee->unit || !$employee->unit->isWithinScope($officeId)) throw new HttpException(422, 'EMPLOYEE_OUTSIDE_PARTICIPANT_DOMAIN');
            }
        }
    }

    private function assertMinimums(Meeting $meeting, array $members): void
    {
        $formalUnits = $this->governance->resolve($meeting)['formal_units'];
        foreach ($formalUnits as $formalUnit) {
            $unitId = (int) $formalUnit->organizational_unit_id;
            $count = collect($members)->filter(fn (array $member): bool => $member['owner_type'] === 'UNIT' && (int) ($member['owner_unit_id'] ?? 0) === $unitId)->count();
            if ($count < 1) throw new HttpException(422, 'UNIT_PARTICIPANT_REQUIRED');
        }
    }

    private function assertProtectedParticipants(Meeting $meeting, array $members): void
    {
        $ids = array_map('intval', array_column($members, 'employee_id'));
        if (!in_array((int) $meeting->chairperson_employee_id, $ids, true)) throw new HttpException(422, 'CHAIRPERSON_PARTICIPANT_REQUIRED');
        $authorityId = (int) ($meeting->authorityContext?->authority_employee_id ?? 0);
        $currentAuthorityMember = $meeting->currentParticipantVersion?->members->firstWhere('employee_id', $authorityId);
        if ($authorityId && $currentAuthorityMember && !in_array($authorityId, $ids, true)) throw new HttpException(422, 'HIGH_AUTHORITY_PARTICIPANT_IMMUTABLE');
    }

    private function memberFromEmployee(Employee $employee, MeetingParticipantChangeCase $case, string $role): array
    {
        $ownerType = $case->owner_type;
        return [
            'employee_id' => (int) $employee->id,
            'organizational_unit_id' => (int) $employee->organizational_unit_id,
            'participant_role' => $role,
            'owner_type' => $ownerType,
            'owner_unit_id' => $ownerType === 'UNIT' ? (int) $case->owner_unit_id : null,
            'is_mandatory' => false,
        ];
    }
}
