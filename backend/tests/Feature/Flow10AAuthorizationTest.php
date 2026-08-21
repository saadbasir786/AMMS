<?php

namespace Tests\Feature;

use App\Models\AmmsRoleScope;
use App\Models\Employee;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Flow10AAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unscoped_human_cannot_view_biometric_metadata(): void
    {
        $employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $response = $this->actingAs(User::query()->where('email', 'system.admin@example.test')->firstOrFail(), 'sanctum')
            ->getJson('/api/employees/'.$employee->id.'/biometrics');
        $response->assertForbidden();
    }

    public function test_unauthorized_human_cannot_start_enrollment(): void
    {
        $employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $this->actingAs(User::query()->where('email', 'system.admin@example.test')->firstOrFail(), 'sanctum')
            ->postJson('/api/biometric/enrollment-sessions', ['employee_id' => $employee->id])
            ->assertForbidden();
    }

    public function test_scoped_enrollment_officer_can_view_metadata_without_template_bytes(): void
    {
        $employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $role = Role::findByName('BIOMETRIC_ENROLLMENT_OFFICER', 'sanctum');
        $actor->assignRole($role);
        AmmsRoleScope::create(['user_id' => $actor->id, 'role_id' => $role->id, 'organizational_unit_id' => $employee->organizational_unit_id]);

        $response = $this->actingAs($actor, 'sanctum')->getJson('/api/employees/'.$employee->id.'/biometrics');
        $response->assertOk()->assertJsonMissingPath('data.templates.0.template_data');
    }

    public function test_parent_directorate_biometric_scope_covers_employee_subunit(): void
    {
        $employee = Employee::query()->where('email', 'employee.a@example.test')->firstOrFail();
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $this->assertNotSame($tech->id, (int) $employee->organizational_unit_id, 'Fixture must prove parent-scope behavior.');

        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $role = Role::findByName('BIOMETRIC_ENROLLMENT_OFFICER', 'sanctum');
        $actor->assignRole($role);
        AmmsRoleScope::create(['user_id' => $actor->id, 'role_id' => $role->id, 'organizational_unit_id' => $tech->id]);

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/employees/'.$employee->id.'/biometrics')
            ->assertOk();
    }
}
