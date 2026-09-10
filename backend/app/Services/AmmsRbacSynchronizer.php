<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\AmmsPermissionRegistry;
use App\Support\AmmsRole;
use App\Models\AmmsRoleDefinition;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class AmmsRbacSynchronizer
{
    /**
     * Synchronize current AMMS RBAC definitions without touching user scopes or assignments.
     * Unknown/custom permissions already attached to canonical roles are preserved.
     */
    public function synchronize(): array
    {
        return DB::transaction(function (): array {
            $permissionNames = AmmsPermissionRegistry::allPermissions();
            foreach ($permissionNames as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'sanctum']);
            }

            $rolesCreated = 0;
            $unknownSystemPermissions = [];
            foreach (AmmsRole::all() as $roleName) {
                $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
                $rolesCreated += $role->wasRecentlyCreated ? 1 : 0;
                AmmsRoleDefinition::updateOrCreate(
                    ['role_id' => $role->id],
                    ['label' => AmmsRole::labels()[$roleName] ?? $roleName, 'role_kind' => AmmsRoleDefinition::SYSTEM, 'status' => AmmsRoleDefinition::ACTIVE],
                );

                // Keep custom permissions, while removing only stale permissions known to
                // this canonical catalogue. No role scope or user assignment is changed.
                $existing = $role->permissions()->pluck('name')->all();
                $custom = array_values(array_diff($existing, $permissionNames));
                if ($custom !== []) $unknownSystemPermissions[$roleName] = $custom;
                $role->syncPermissions(array_values(array_unique(array_merge(
                    AmmsPermissionRegistry::permissionsForRole($roleName),
                    $custom,
                ))));
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return [
                'permissions' => count($permissionNames),
                'roles' => count(AmmsRole::all()),
                'roles_created' => $rolesCreated,
                'custom_roles_preserved' => Role::query()->where('guard_name', 'sanctum')->whereNotIn('name', AmmsRole::all())->count(),
                'unknown_system_permissions' => $unknownSystemPermissions,
            ];
        });
    }
}
