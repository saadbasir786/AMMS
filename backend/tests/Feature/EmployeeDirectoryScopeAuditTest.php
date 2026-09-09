<?php

namespace Tests\Feature;

use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDirectoryScopeAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_employee_resource_exposes_actual_unit_and_directorate_ancestry_for_scoped_pickers(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $software = OrganizationalUnit::where('code', 'TECH-SW')->firstOrFail();

        $response = $this->actingAs($techExec, 'sanctum')->getJson('/api/employees')->assertOk();
        $employees = collect($response->json('data'));
        $employee = $employees->firstWhere('employee_number', 'EMP-TECH-003');

        $this->assertNotNull($employee);
        $this->assertSame($software->id, (int) data_get($employee, 'organizational_unit_id'));
        $this->assertSame($software->id, (int) data_get($employee, 'unit.id'));
        $this->assertContains($software->id, array_map('intval', data_get($employee, 'unit_scope_ids', [])));
        $this->assertContains($tech->id, array_map('intval', data_get($employee, 'unit_scope_ids', [])));
    }

    public function test_employee_directory_supports_server_pagination_search_and_bounds(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();

        $response = $this->actingAs($techExec, 'sanctum')
            ->getJson('/api/employees?search=EMP-TECH-003&page=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data');

        $this->assertSame('EMP-TECH-003', $response->json('data.0.employee_number'));

        $this->actingAs($techExec, 'sanctum')
            ->getJson('/api/employees?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_employee_directory_requires_authentication(): void
    {
        $this->getJson('/api/employees')->assertUnauthorized();
    }
}
