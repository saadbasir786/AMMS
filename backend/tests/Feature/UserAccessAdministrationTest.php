<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccessAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_system_admin_can_create_a_scoped_employee_account_and_audit_role_assignment_and_revocation(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $employee = Employee::query()->where('email', 'employee.b@example.test')->firstOrFail();
        $technology = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();

        $user = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users', [
            'name' => 'حساب مریم نوری',
            'email' => 'maryam.nouri@example.test',
            'password' => 'SecurePass!2026',
            'employee_id' => $employee->id,
        ])->assertCreated()->assertJsonPath('data.employee_id', $employee->id);

        $assignment = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users/'.$user->json('data.id').'/roles', [
            'role' => 'EMPLOYEE',
            'organizational_unit_id' => $technology->id,
        ])->assertCreated()->assertJsonPath('data.role', 'EMPLOYEE');

        $this->assertDatabaseHas('user_access_audits', [
            'user_id' => $user->json('data.id'),
            'actor_id' => $admin->id,
            'action' => 'ROLE_ASSIGNED',
            'role' => 'EMPLOYEE',
            'organizational_unit_id' => $technology->id,
        ]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/users/'.$user->json('data.id').'/access-audits')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'ROLE_ASSIGNED');

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users/'.$user->json('data.id').'/roles', [
            'role' => 'DIRECTOR',
            'organizational_unit_id' => $technology->id,
        ])->assertStatus(422);

        $this->actingAs($admin, 'sanctum')->deleteJson('/api/admin/users/'.$user->json('data.id').'/roles/'.$assignment->json('data.id'))
            ->assertOk();

        $this->assertDatabaseHas('user_access_audits', [
            'user_id' => $user->json('data.id'),
            'action' => 'ROLE_REVOKED',
            'role' => 'EMPLOYEE',
            'organizational_unit_id' => $technology->id,
        ]);
    }

    public function test_technical_permissions_without_system_admin_role_cannot_manage_accounts(): void
    {
        $executive = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $executive->givePermissionTo(['user.manage', 'role.manage', 'employee.manage']);

        $this->actingAs($executive, 'sanctum')->getJson('/api/admin/users')->assertForbidden();
        $this->actingAs($executive, 'sanctum')->getJson('/api/admin/employees')->assertForbidden();
    }

    public function test_last_active_system_admin_cannot_be_deactivated_or_have_its_role_revoked(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $scope = $admin->roleScopes()->whereHas('role', fn ($query) => $query->where('name', 'SYSTEM_ADMIN'))->firstOrFail();

        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/users/'.$admin->id, [
            'name' => $admin->name,
            'email' => $admin->email,
            'status' => 'INACTIVE',
        ])->assertStatus(422);

        $this->actingAs($admin, 'sanctum')->deleteJson('/api/admin/users/'.$admin->id.'/roles/'.$scope->id)->assertStatus(422);
    }

    public function test_deactivating_a_business_account_revokes_its_obsolete_role_scope(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $executive = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();

        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/users/'.$executive->id, [
            'name' => $executive->name,
            'email' => $executive->email,
            'employee_id' => $executive->employee_id,
            'status' => 'INACTIVE',
        ])->assertOk()->assertJsonPath('data.status', 'INACTIVE');

        $this->assertDatabaseMissing('amms_role_scopes', ['user_id' => $executive->id]);
        $this->assertDatabaseHas('user_access_audits', ['user_id' => $executive->id, 'action' => 'ROLE_REVOKED']);
    }
}
