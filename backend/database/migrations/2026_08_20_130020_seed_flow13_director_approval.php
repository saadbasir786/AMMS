<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $role = Role::where('name', 'DIRECTOR')->where('guard_name', 'sanctum')->first();
        if ($role) $role->givePermissionTo('meeting.exception.approve');
    }

    public function down(): void
    {
        // Permission remains for audit-safe rollback.
    }
};
