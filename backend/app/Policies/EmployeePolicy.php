<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function before(User $user): ?bool
    {
        if (!$user->isActive()) return false;
        return $user->hasScopedRole('SYSTEM_ADMIN') && $user->can('employee.manage') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isActive() && ($user->can('employee.manage') || $user->can('meeting-request.view') || ($user->can('authority-participant.manage') && (filled($user->scopedRoleUnitIds('AUTHORITY_OFFICE')) || filled($user->scopedRoleUnitIds('EXECUTIVE_SECRETARY')))));
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->isActive() && ($user->can('employee.manage') || $user->employee_id === $employee->id || collect(['DIRECTOR', 'EXECUTIVE_MANAGEMENT', 'MEETING_COORDINATOR'])->contains(fn (string $role) => $user->hasScopedRole($role, $employee->organizational_unit_id)));
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->can('employee.manage');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->isActive() && $user->can('employee.manage');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return false;
    }
}
