<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::query()->where(['name' => 'EXECUTIVE_SECRETARY', 'guard_name' => 'sanctum'])->first();
        if (!$role) return;

        foreach ([
            'meeting-participant-change.create',
            'meeting-participant-change.manage',
            'meeting-participant-change.submit',
            'meeting-participant-change.confirm',
            'meeting-participant-change.return',
            'meeting-participant-change.reject',
        ] as $permissionName) {
            $permission = Permission::query()->where(['name' => $permissionName, 'guard_name' => 'sanctum'])->first();
            if ($permission) $role->revokePermissionTo($permission);
        }
    }

    public function down(): void
    {
        // Deliberately irreversible: restoring Executive Secretary participant ownership
        // would violate the frozen FLOW-06 Authority Office semantics.
    }
};
