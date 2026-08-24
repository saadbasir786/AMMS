<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\ScopedCapabilityManager;
use App\Support\SpecialCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Flow10AAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unscoped_system_admin_cannot_view_biometric_metadata(): void
    {
        $employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $response = $this->actingAs(User::query()->where('email', 'system.admin@example.test')->firstOrFail(), 'sanctum')
            ->getJson('/api/employees/'.$employee->id.'/biometrics');
        $response->assertForbidden();
    }

    public function test_permission_without_scoped_capability_cannot_start_enrollment(): void
    {
        $employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $actor->givePermissionTo('biometric.enroll');

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/biometric/enrollment-sessions', ['employee_id' => $employee->id])
            ->assertForbidden();
    }

    public function test_delegated_executive_manager_can_view_scoped_metadata_without_template_bytes(): void
    {
        $employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $grantor = User::query()->where('email', 'system.admin@example.test')->firstOrFail();

        app(ScopedCapabilityManager::class)->grant($actor, SpecialCapability::BIOMETRIC_ENROLLMENT, $tech, $grantor);

        $response = $this->actingAs($actor, 'sanctum')->getJson('/api/employees/'.$employee->id.'/biometrics');
        $response->assertOk()->assertJsonMissingPath('data.templates.0.template_data');
    }

    public function test_parent_directorate_capability_scope_covers_employee_subunit(): void
    {
        $employee = Employee::query()->where('email', 'employee.a@example.test')->firstOrFail();
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $this->assertNotSame($tech->id, (int) $employee->organizational_unit_id, 'Fixture must prove parent-scope behavior.');

        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $grantor = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        app(ScopedCapabilityManager::class)->grant($actor, SpecialCapability::BIOMETRIC_ENROLLMENT, $tech, $grantor);

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/employees/'.$employee->id.'/biometrics')
            ->assertOk();
    }

    public function test_capability_scope_does_not_cross_directorates(): void
    {
        $financeEmployee = Employee::query()->where('email', 'finance.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $grantor = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        app(ScopedCapabilityManager::class)->grant($actor, SpecialCapability::BIOMETRIC_ENROLLMENT, $tech, $grantor);

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/employees/'.$financeEmployee->id.'/biometrics')
            ->assertForbidden();
    }

    public function test_artificial_biometric_role_is_retired(): void
    {
        $this->assertDatabaseMissing('roles', [
            'name' => 'BIOMETRIC_ENROLLMENT_OFFICER',
            'guard_name' => 'sanctum',
        ]);
        $this->assertDatabaseMissing('user_roles', ['role' => 'BIOMETRIC_ENROLLMENT_OFFICER']);
    }
}
