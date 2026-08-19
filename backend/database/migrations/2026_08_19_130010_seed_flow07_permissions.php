<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'meeting-reschedule.create', 'meeting-reschedule.propose', 'meeting-reschedule.acknowledge',
            'meeting-reschedule.request-alternative', 'meeting-reschedule.confirm', 'meeting-reschedule.view-history',
        ];
        foreach ($permissions as $permission) Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        $rolePermissions = [
            'SYSTEM_ADMIN' => $permissions,
            'AUTHORITY_OFFICE' => ['meeting-reschedule.create', 'meeting-reschedule.view-history'],
            'HIGH_AUTHORITY' => ['meeting-reschedule.view-history'],
            'EXECUTIVE_SECRETARY' => $permissions,
            'DIRECTOR' => ['meeting-reschedule.create', 'meeting-reschedule.acknowledge', 'meeting-reschedule.request-alternative', 'meeting-reschedule.confirm', 'meeting-reschedule.view-history'],
            'EXECUTIVE_MANAGEMENT' => ['meeting-reschedule.create', 'meeting-reschedule.propose', 'meeting-reschedule.acknowledge', 'meeting-reschedule.request-alternative', 'meeting-reschedule.view-history'],
            'MEETING_COORDINATOR' => $permissions,
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
