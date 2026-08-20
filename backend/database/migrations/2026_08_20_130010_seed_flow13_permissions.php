<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'meeting.exception.view', 'meeting.exception.create', 'meeting.exception.update-draft',
            'meeting.exception.submit', 'meeting.exception.review', 'meeting.exception.approve',
            'meeting.exception.acknowledge', 'meeting.exception.apply', 'meeting.exception.withdraw',
            'meeting.exception.history.view', 'meeting.postpone', 'meeting.cancel', 'meeting.reopen', 'meeting.resume',
        ];
        foreach ($permissions as $permission) Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);

        $rolePermissions = [
            'MEETING_COORDINATOR' => [
                'meeting.exception.view', 'meeting.exception.create', 'meeting.exception.update-draft',
                'meeting.exception.submit', 'meeting.exception.apply', 'meeting.exception.withdraw',
                'meeting.exception.history.view', 'meeting.postpone', 'meeting.cancel', 'meeting.reopen', 'meeting.resume',
            ],
            'EXECUTIVE_SECRETARY' => [
                'meeting.exception.view', 'meeting.exception.create', 'meeting.exception.update-draft',
                'meeting.exception.submit', 'meeting.exception.apply', 'meeting.exception.withdraw',
                'meeting.exception.history.view', 'meeting.postpone', 'meeting.cancel', 'meeting.reopen', 'meeting.resume',
            ],
            'DIRECTOR' => ['meeting.exception.view', 'meeting.exception.review', 'meeting.exception.acknowledge', 'meeting.exception.history.view'],
            'HIGH_AUTHORITY' => ['meeting.exception.view', 'meeting.exception.review', 'meeting.exception.approve', 'meeting.exception.history.view'],
            'AUTHORITY_OFFICE' => ['meeting.exception.view', 'meeting.exception.history.view'],
        ];
        foreach ($rolePermissions as $roleName => $names) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
            $role->givePermissionTo($names);
        }
    }

    public function down(): void
    {
        // FLOW-13 permissions remain for audit-safe rollback.
    }
};
