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
            'meeting.execution.start',
            'meeting.execution.end',
            'meeting.minutes.view',
            'meeting.minutes.prepare',
            'meeting.minutes.submit',
            'meeting.minutes.review',
            'meeting.minutes.approve_authority',
            'meeting.minutes.finalize',
            'meeting.minutes.document.view',
        ];
        foreach ($permissions as $permission) Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);

        $rolePermissions = [
            'MEETING_COORDINATOR' => [
                'meeting.execution.start', 'meeting.execution.end', 'meeting.minutes.view',
                'meeting.minutes.prepare', 'meeting.minutes.submit', 'meeting.minutes.finalize',
                'meeting.minutes.document.view',
            ],
            'EXECUTIVE_SECRETARY' => [
                'meeting.execution.start', 'meeting.execution.end', 'meeting.minutes.view',
                'meeting.minutes.prepare', 'meeting.minutes.submit', 'meeting.minutes.review',
                'meeting.minutes.finalize', 'meeting.minutes.document.view',
            ],
            'DIRECTOR' => ['meeting.minutes.view', 'meeting.minutes.review', 'meeting.minutes.document.view'],
            'HIGH_AUTHORITY' => ['meeting.minutes.view', 'meeting.minutes.approve_authority', 'meeting.minutes.document.view'],
            'AUTHORITY_OFFICE' => ['meeting.minutes.view', 'meeting.minutes.document.view'],
        ];
        foreach ($rolePermissions as $roleName => $names) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
            $role->givePermissionTo($names);
        }
    }

    public function down(): void
    {
        // FLOW-11 permissions remain for audit-safe rollback.
    }
};
