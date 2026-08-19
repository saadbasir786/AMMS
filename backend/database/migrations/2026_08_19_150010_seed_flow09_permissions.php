<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'terminal.view',
            'terminal.register',
            'terminal.manage',
            'terminal.rotate-credential',
            'terminal.revoke',
            'meeting-terminal.view',
            'meeting-terminal.assign',
            'meeting-terminal.refresh',
            'meeting-terminal.revoke',
            'terminal-session.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        $rolePermissions = [
            'SYSTEM_ADMIN' => $permissions,
            'EXECUTIVE_SECRETARY' => [
                'meeting-terminal.view',
                'meeting-terminal.assign',
                'meeting-terminal.refresh',
                'meeting-terminal.revoke',
                'terminal-session.view',
            ],
            'EXECUTIVE_MANAGEMENT' => [
                'meeting-terminal.view',
                'meeting-terminal.assign',
                'meeting-terminal.refresh',
                'meeting-terminal.revoke',
                'terminal-session.view',
            ],
            'MEETING_COORDINATOR' => [
                'meeting-terminal.view',
                'meeting-terminal.assign',
                'meeting-terminal.refresh',
                'meeting-terminal.revoke',
                'terminal-session.view',
            ],
        ];

        foreach ($rolePermissions as $roleName => $names) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
            $role->givePermissionTo($names);
        }
    }

    public function down(): void
    {
        // FLOW-09 permissions remain for audit-safe rollback.
    }
};
