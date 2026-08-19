<?php

namespace App\Policies;

use App\Models\BiometricEnrollmentSession;
use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use App\Models\User;

class BiometricPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasPermissionTo('biometric.status.view', 'sanctum');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->employeeScoped($user, $employee, 'biometric.status.view');
    }

    public function enroll(User $user, Employee $employee): bool
    {
        return $this->employeeScoped($user, $employee, 'biometric.enroll');
    }

    public function reenroll(User $user, Employee $employee): bool
    {
        return $this->employeeScoped($user, $employee, 'biometric.reenroll');
    }

    public function revoke(User $user, EmployeeBiometricTemplate $template): bool
    {
        return $this->employeeScoped($user, $template->employee, 'biometric.revoke');
    }

    public function cancel(User $user, BiometricEnrollmentSession $session): bool
    {
        return $session->started_by_user_id === $user->id
            && $user->isActive()
            && $user->hasPermissionTo('biometric.enrollment.cancel', 'sanctum');
    }

    private function employeeScoped(User $user, Employee $employee, string $permission): bool
    {
        if (!$user->isActive() || !$user->hasPermissionTo($permission, 'sanctum')) return false;
        $unitId = (int) $employee->organizational_unit_id;
        return $user->hasScopedRole('BIOMETRIC_ENROLLMENT_OFFICER', $unitId);
    }
}
