<?php

use App\Models\AmmsRoleScope;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        $oldRoleName = 'MEETING_COORDINATOR';
        $newRoleName = 'EXECUTIVE_MANAGEMENT';

        DB::transaction(function () use ($oldRoleName, $newRoleName): void {
            $oldRole = Role::where('name', $oldRoleName)->where('guard_name', 'sanctum')->first();
            $newRole = Role::firstOrCreate(['name' => $newRoleName, 'guard_name' => 'sanctum']);

            if (!$oldRole) {
                return;
            }

            // Transfer the complete former coordinator permission set without duplicates.
            $permissions = $oldRole->permissions()->pluck('name')->all();
            if ($permissions) {
                $newRole->givePermissionTo($permissions);
            }

            // Transfer Spatie assignments before removing the old role.
            DB::table('model_has_roles')
                ->where('role_id', $oldRole->id)
                ->where('model_type', User::class)
                ->get()
                ->each(function (object $assignment) use ($newRole): void {
                    $exists = DB::table('model_has_roles')
                        ->where('role_id', $newRole->id)
                        ->where('model_id', $assignment->model_id)
                        ->where('model_type', User::class)
                        ->exists();

                    if (!$exists) {
                        DB::table('model_has_roles')->insert([
                            'role_id' => $newRole->id,
                            'model_id' => $assignment->model_id,
                            'model_type' => User::class,
                        ]);
                    }
                });

            // Transfer scoped AMMS role metadata, preserving user and unit boundaries.
            AmmsRoleScope::where('role_id', $oldRole->id)
                ->get()
                ->each(function (AmmsRoleScope $scope) use ($newRole): void {
                    AmmsRoleScope::firstOrCreate([
                        'user_id' => $scope->user_id,
                        'role_id' => $newRole->id,
                        'organizational_unit_id' => $scope->organizational_unit_id,
                    ]);
                });

            // Migrate the legacy assignment representation without touching unrelated roles.
            DB::table('user_roles')
                ->where('role', $oldRoleName)
                ->update(['role' => $newRoleName]);

            // Retire active assignments, scopes, permissions, and finally the old role.
            DB::table('model_has_roles')->where('role_id', $oldRole->id)->delete();
            AmmsRoleScope::where('role_id', $oldRole->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $oldRole->id)->delete();
            DB::table('roles')->where('id', $oldRole->id)->delete();
        });

        Log::info('AMMS role-model consolidation completed', [
            'absorbed_role' => $oldRoleName,
            'target_role' => $newRoleName,
        ]);
    }

    public function down(): void
    {
        // Deliberately non-destructive. Restoring the deprecated role would require
        // an explicit migration decision and must not guess historical assignments.
    }
};
