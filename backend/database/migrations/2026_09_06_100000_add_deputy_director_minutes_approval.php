<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['meeting.view', 'meeting.minutes.view', 'meeting.minutes.review', 'meeting.minutes.review-delegated'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
        }

        $role = Role::firstOrCreate(['name' => 'DEPUTY_DIRECTOR', 'guard_name' => 'sanctum']);
        $role->givePermissionTo(['meeting.view', 'meeting.minutes.view', 'meeting.minutes.review']);
    }

    public function down(): void
    {
        // Role and historical assignments are retained for audit safety.
    }
};
