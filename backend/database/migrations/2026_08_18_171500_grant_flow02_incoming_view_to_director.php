<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'meeting-request.view-incoming',
            'guard_name' => 'sanctum',
        ]);

        $director = Role::where([
            'name' => 'DIRECTOR',
            'guard_name' => 'sanctum',
        ])->first();

        if ($director && !$director->hasPermissionTo($permission)) {
            $director->givePermissionTo($permission);
        }
    }

    public function down(): void
    {
        $director = Role::where([
            'name' => 'DIRECTOR',
            'guard_name' => 'sanctum',
        ])->first();

        if ($director) {
            $director->revokePermissionTo('meeting-request.view-incoming');
        }
    }
};
