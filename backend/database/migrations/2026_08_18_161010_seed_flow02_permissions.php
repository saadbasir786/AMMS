<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'meeting-request.view-incoming',
            'meeting-request.approve-target',
            'meeting-request.return-target',
            'meeting-request.reject-target',
            'meeting-schedule.propose',
            'meeting-schedule.accept',
            'meeting-participant.nominate',
            'meeting-participant.confirm',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        $rolePermissions = [
            'EXECUTIVE_MANAGEMENT' => $permissions,
            'DIRECTOR' => [
                'meeting-request.approve-target',
                'meeting-request.return-target',
                'meeting-request.reject-target',
            ],
        ];

        foreach ($rolePermissions as $roleName => $names) {
            $role = Role::where(['name' => $roleName, 'guard_name' => 'sanctum'])->first();
            if ($role) {
                $role->givePermissionTo($names);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive rollback: FLOW-02 permission rows and assignments remain for audit-safe rollback.
    }
};
