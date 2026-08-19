<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'authority-initiated-meeting.prepare', 'authority-initiated-meeting.issue', 'authority-initiated-meeting.distribute',
            'authority-directive.view', 'authority-directive.coordinate', 'authority-directive.acknowledge',
            'authority-directive.clarification-request', 'authority-directive.clarification-respond',
        ];
        foreach ($permissions as $permission) Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        $rolePermissions = [
            'SYSTEM_ADMIN' => $permissions,
            'AUTHORITY_OFFICE' => ['authority-initiated-meeting.prepare', 'authority-initiated-meeting.distribute', 'authority-directive.view', 'authority-directive.clarification-respond'],
            'HIGH_AUTHORITY' => ['authority-directive.view', 'authority-initiated-meeting.issue'],
            'EXECUTIVE_SECRETARY' => ['authority-directive.view'],
            'DIRECTOR' => ['authority-directive.view', 'authority-directive.acknowledge', 'authority-directive.clarification-request'],
            'EXECUTIVE_MANAGEMENT' => ['authority-directive.view', 'authority-directive.coordinate'],
            'MEETING_COORDINATOR' => ['authority-directive.view', 'authority-directive.coordinate'],
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
