<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['authority-schedule.acknowledge'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }
        foreach (['EXECUTIVE_MANAGEMENT', 'MEETING_COORDINATOR'] as $roleName) {
            $role = Role::where(['name' => $roleName, 'guard_name' => 'sanctum'])->first();
            if ($role) $role->givePermissionTo('authority-schedule.acknowledge');
        }
    }

    public function down(): void
    {
        // Non-destructive rollback.
    }
};
