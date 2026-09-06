<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_employee_administration_requires_a_matching_unit_position_and_supports_deactivation(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $executive = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $technology = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $financePosition = Position::query()->where('organizational_unit_id', OrganizationalUnit::query()->where('code', 'FIN')->value('id'))->firstOrFail();
        $technologyPosition = Position::create([
            'organizational_unit_id' => $technology->id,
            'title' => 'کارشناس برنامه‌ریزی',
            'position_type' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);

        $payload = [
            'employee_number' => 'EMP-TECH-999',
            'first_name' => 'مریم',
            'last_name' => 'نعمتی',
            'organizational_unit_id' => $technology->id,
            'position_id' => $financePosition->id,
        ];

        $this->actingAs($executive, 'sanctum')->postJson('/api/admin/employees', $payload)->assertForbidden();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/employees', $payload)->assertStatus(422)->assertJsonValidationErrors('position_id');

        $payload['position_id'] = $technologyPosition->id;
        $employee = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/employees', $payload)
            ->assertCreated()
            ->assertJsonPath('data.position.id', $technologyPosition->id);

        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/employees/'.$employee->json('data.id'), [
            ...$payload,
            'status' => 'INACTIVE',
        ])->assertOk()->assertJsonPath('data.status', 'INACTIVE');
    }

    public function test_employee_transfer_revokes_obsolete_role_scope_and_records_it(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $executive = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $employee = $executive->employee;
        $financePosition = Position::query()->where('position_type', 'EXECUTIVE_MANAGEMENT')->whereHas('unit', fn ($query) => $query->where('code', 'FIN-EXEC'))->firstOrFail();

        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/employees/'.$employee->id, [
            'employee_number' => $employee->employee_number,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'father_name' => $employee->father_name,
            'organizational_unit_id' => $financePosition->organizational_unit_id,
            'position_id' => $financePosition->id,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'status' => 'ACTIVE',
        ])->assertOk();

        $this->assertDatabaseMissing('amms_role_scopes', ['user_id' => $executive->id]);
        $this->assertDatabaseHas('user_access_audits', ['user_id' => $executive->id, 'action' => 'ROLE_REVOKED']);
    }
}
