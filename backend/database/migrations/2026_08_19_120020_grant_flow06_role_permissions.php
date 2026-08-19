<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $grants = [
            'HIGH_AUTHORITY' => ['authority-initiated-meeting.prepare'],
            'DIRECTOR' => ['authority-schedule.acknowledge', 'meeting-participant.nominate', 'meeting-participant.confirm'],
        ];
        foreach ($grants as $roleName => $permissions) {
            $role = Role::where(['name' => $roleName, 'guard_name' => 'sanctum'])->first();
            if ($role) $role->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        // Non-destructive rollback.
    }
};
