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
}
