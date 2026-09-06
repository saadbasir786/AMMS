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
}
