<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'biometric.status.view',
            'biometric.enroll',
            'biometric.reenroll',
            'biometric.revoke',
            'biometric.enrollment.cancel',
            'attendance.view',
            'attendance.override',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        $rolePermissions = [
            'SYSTEM_ADMIN' => ['biometric.status.view'],
            'BIOMETRIC_ENROLLMENT_OFFICER' => [
                'biometric.status.view',
                'biometric.enroll',
                'biometric.reenroll',
                'biometric.revoke',
                'biometric.enrollment.cancel',
            ],
            'EXECUTIVE_SECRETARY' => ['biometric.status.view', 'attendance.view', 'attendance.override'],
            'MEETING_COORDINATOR' => ['biometric.status.view', 'attendance.view', 'attendance.override'],
            'EXECUTIVE_MANAGEMENT' => ['biometric.status.view', 'attendance.view'],
        ];

        foreach ($rolePermissions as $roleName => $names) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
            $role->givePermissionTo($names);
        }
    }

    public function down(): void
    {
        // FLOW-10 permissions and role grants remain for audit-safe rollback.
    }
};
