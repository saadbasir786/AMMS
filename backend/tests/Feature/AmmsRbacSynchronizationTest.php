<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AmmsRbacSynchronizer;
use App\Support\AmmsPermissionRegistry;
use App\Support\AmmsRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AmmsRbacSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbac_synchronization_is_idempotent_and_preserves_custom_permissions(): void
    {
        $custom = Permission::create(['name' => 'custom.test.permission', 'guard_name' => 'sanctum']);
        $role = Role::firstOrCreate(['name' => AmmsRole::DIRECTOR, 'guard_name' => 'sanctum']);
        $role->givePermissionTo($custom);

        $synchronizer = app(AmmsRbacSynchronizer::class);
        $first = $synchronizer->synchronize();
        $firstPivotCount = (int) $this->app['db']->table('role_has_permissions')->count();
        $second = $synchronizer->synchronize();
        $secondPivotCount = (int) $this->app['db']->table('role_has_permissions')->count();

        $this->assertSame($first['permissions'], count(AmmsPermissionRegistry::allPermissions()));
        $this->assertSame($first['roles'], count(AmmsRole::all()));
        $this->assertSame(0, $second['roles_created']);
        $this->assertSame($firstPivotCount, $secondPivotCount);
        $this->assertTrue(Role::findByName(AmmsRole::DIRECTOR, 'sanctum')->hasPermissionTo($custom));
        $this->assertCount(count(AmmsRole::all()), Role::whereIn('name', AmmsRole::all())->get());
    }

    public function test_registry_matches_current_migrated_role_permission_state(): void
    {
        foreach (AmmsRole::all() as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'sanctum')
                ->firstOrFail();

            $actual = $role->permissions()->pluck('name')->sort()->values()->all();
            $expected = collect(AmmsPermissionRegistry::permissionsForRole($roleName))
                ->sort()
                ->values()
                ->all();

            $this->assertSame($expected, $actual, "Permission mapping drifted for {$roleName}.");
        }
    }
}
