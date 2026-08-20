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
            'reporting.dashboard.executive',
            'reporting.dashboard.directorate',
            'reporting.dashboard.personal',
            'reporting.dashboard.authority',
            'reporting.dashboard.technical',
            'reporting.meetings.view',
            'reporting.requests.view',
            'reporting.attendance.summary',
            'reporting.attendance.detail',
            'reporting.decisions.view',
            'reporting.action-items.view',
            'reporting.exceptions.view',
            'reporting.exports.create',
            'reporting.exports.download',
            'reporting.technical.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        $grants = [
            'SYSTEM_ADMIN' => ['reporting.dashboard.technical', 'reporting.technical.view'],
            'EXECUTIVE_MANAGEMENT' => [
                'reporting.dashboard.executive', 'reporting.meetings.view', 'reporting.requests.view',
                'reporting.attendance.summary', 'reporting.decisions.view', 'reporting.action-items.view',
                'reporting.exceptions.view', 'reporting.exports.create', 'reporting.exports.download',
            ],
            'HIGH_AUTHORITY' => [
                'reporting.dashboard.executive', 'reporting.dashboard.authority', 'reporting.meetings.view',
                'reporting.decisions.view', 'reporting.action-items.view', 'reporting.exceptions.view',
                'reporting.exports.create', 'reporting.exports.download',
            ],
            'AUTHORITY_OFFICE' => [
                'reporting.dashboard.authority', 'reporting.meetings.view', 'reporting.requests.view',
                'reporting.decisions.view', 'reporting.action-items.view', 'reporting.exceptions.view',
                'reporting.exports.create', 'reporting.exports.download',
            ],
            'EXECUTIVE_SECRETARY' => [
                'reporting.dashboard.authority', 'reporting.meetings.view', 'reporting.requests.view',
                'reporting.decisions.view', 'reporting.exceptions.view',
            ],
            'DIRECTOR' => [
                'reporting.dashboard.directorate', 'reporting.meetings.view', 'reporting.requests.view',
                'reporting.attendance.summary', 'reporting.decisions.view', 'reporting.action-items.view',
                'reporting.exceptions.view', 'reporting.exports.create', 'reporting.exports.download',
            ],
            'MEETING_COORDINATOR' => [
                'reporting.dashboard.directorate', 'reporting.meetings.view', 'reporting.requests.view',
                'reporting.attendance.summary', 'reporting.action-items.view',
            ],
            'EMPLOYEE' => ['reporting.dashboard.personal', 'reporting.meetings.view', 'reporting.action-items.view'],
        ];

        foreach ($grants as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
            $role->givePermissionTo($permissionNames);
        }
    }

    public function down(): void
    {
        // Additive permission migrations intentionally retain permissions on rollback.
    }
};
