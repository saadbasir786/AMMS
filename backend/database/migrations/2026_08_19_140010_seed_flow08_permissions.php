<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'meeting-participant-change.create',
            'meeting-participant-change.manage',
            'meeting-participant-change.submit',
            'meeting-participant-change.confirm',
            'meeting-participant-change.return',
            'meeting-participant-change.reject',
            'meeting-participant-change.view-history',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        $rolePermissions = [
            'SYSTEM_ADMIN' => $permissions,
            'AUTHORITY_OFFICE' => $permissions,
            'EXECUTIVE_SECRETARY' => $permissions,
            'EXECUTIVE_MANAGEMENT' => [
                'meeting-participant-change.create',
                'meeting-participant-change.manage',
                'meeting-participant-change.submit',
                'meeting-participant-change.view-history',
            ],
            'MEETING_COORDINATOR' => [
                'meeting-participant-change.create',
                'meeting-participant-change.manage',
                'meeting-participant-change.submit',
                'meeting-participant-change.view-history',
            ],
            'DIRECTOR' => [
                'meeting-participant-change.confirm',
                'meeting-participant-change.return',
                'meeting-participant-change.reject',
                'meeting-participant-change.view-history',
            ],
            'HIGH_AUTHORITY' => ['meeting-participant-change.view-history'],
        ];

        foreach ($rolePermissions as $roleName => $names) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
            $role->givePermissionTo($names);
        }
    }

    public function down(): void
    {
        // Permissions remain for audit-safe rollback.
    }
};
