<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'authority-request.view',
            'authority-request.review',
            'authority-request.forward',
            'authority-request.return',
            'authority-request.decide',
            'authority-request.approve',
            'authority-request.reject',
            'authority-request.return-by-authority',
            'authority-schedule.view',
            'authority-schedule.propose',
            'authority-schedule.confirm',
            'authority-participant.manage',
            'authority-participant.confirm',
            'authority-meeting.finalize',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        $rolePermissions = [
            'SYSTEM_ADMIN' => $permissions,
            'AUTHORITY_OFFICE' => [
                'authority-request.view', 'authority-request.review', 'authority-request.forward', 'authority-request.return',
                'authority-schedule.view', 'authority-participant.manage', 'authority-participant.confirm',
            ],
            'EXECUTIVE_SECRETARY' => [
                'authority-request.view', 'authority-schedule.view', 'authority-schedule.propose', 'authority-schedule.confirm',
                'authority-participant.manage', 'authority-participant.confirm', 'authority-meeting.finalize',
            ],
            'HIGH_AUTHORITY' => [
                'authority-request.view', 'authority-request.decide', 'authority-request.approve',
                'authority-request.reject', 'authority-request.return-by-authority',
            ],
        ];

        foreach ($rolePermissions as $roleName => $names) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
            $role->givePermissionTo($names);
        }
    }

    public function down(): void
    {
        // Non-destructive rollback: permission rows and assignments remain for audit-safe rollback.
    }
};
