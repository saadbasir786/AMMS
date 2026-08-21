<?php

use App\Models\AmmsRoleScope;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function (): void {
            $user = User::where('email', 'tech.coordinator@example.test')->first();

            if (!$user) {
                return;
            }

            $role = DB::table('roles')
                ->where('name', 'EXECUTIVE_MANAGEMENT')
                ->where('guard_name', 'sanctum')
                ->first();

            if ($role) {
                DB::table('model_has_roles')
                    ->where('model_type', User::class)
                    ->where('model_id', $user->id)
                    ->where('role_id', $role->id)
                    ->delete();

                AmmsRoleScope::query()
                    ->where('user_id', $user->id)
                    ->where('role_id', $role->id)
                    ->delete();
            }

            DB::table('user_roles')
                ->where('user_id', $user->id)
                ->where('role', 'EXECUTIVE_MANAGEMENT')
                ->delete();
        });
    }

    public function down(): void
    {
        // Deliberately non-destructive: restoring a retired test authority would
        // recreate the artificial coordinator identity this migration removes.
    }
};
