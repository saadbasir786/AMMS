<?php

use App\Models\AmmsCapabilityScope;
use App\Models\AmmsRoleScope;
use App\Models\User;
use App\Support\SpecialCapability;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amms_capability_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('capability', 100);
            $table->foreignId('organizational_unit_id')->constrained('organizational_units')->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'capability', 'organizational_unit_id', 'revoked_at'], 'amms_capability_user_scope_idx');
            $table->index(['organizational_unit_id', 'capability', 'revoked_at'], 'amms_capability_unit_scope_idx');
        });

        DB::transaction(function (): void {
            $oldRole = Role::query()
                ->where('name', 'BIOMETRIC_ENROLLMENT_OFFICER')
                ->where('guard_name', 'sanctum')
                ->first();

            if (!$oldRole) {
                return;
            }

            $permissions = SpecialCapability::permissions(SpecialCapability::BIOMETRIC_ENROLLMENT);

            AmmsRoleScope::query()
                ->where('role_id', $oldRole->id)
                ->whereNotNull('organizational_unit_id')
                ->get()
                ->each(function (AmmsRoleScope $legacyScope) use ($permissions): void {
                    AmmsCapabilityScope::firstOrCreate(
                        [
                            'user_id' => $legacyScope->user_id,
                            'capability' => SpecialCapability::BIOMETRIC_ENROLLMENT,
                            'organizational_unit_id' => $legacyScope->organizational_unit_id,
                            'revoked_at' => null,
                        ],
                        [
                            'granted_by_user_id' => null,
                            'granted_at' => $legacyScope->created_at ?? now(),
                        ]
                    );

                    $user = User::query()->find($legacyScope->user_id);
                    if ($user) {
                        $user->givePermissionTo($permissions);
                    }
                });

            // Unscoped legacy assignments are deliberately not converted into global capability access.
            DB::table('user_roles')->where('role', 'BIOMETRIC_ENROLLMENT_OFFICER')->delete();
            DB::table('model_has_roles')->where('role_id', $oldRole->id)->delete();
            AmmsRoleScope::query()->where('role_id', $oldRole->id)->delete();
            DB::table('role_has_permissions')->where('role_id', $oldRole->id)->delete();
            DB::table('roles')->where('id', $oldRole->id)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Intentionally non-destructive: restoring an artificial organizational role would
        // reintroduce a retired authorization model and could misrepresent audit history.
    }
};
