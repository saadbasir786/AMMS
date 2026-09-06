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
}
