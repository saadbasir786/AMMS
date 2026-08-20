<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleModelConsolidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_deprecated_role_is_not_active_or_assignable_and_permissions_are_on_executive_management(): void
    {
        $expectedRoles = [
            'SYSTEM_ADMIN',
            'EMPLOYEE',
            'DIRECTOR',
            'EXECUTIVE_MANAGEMENT',
            'AUTHORITY_OFFICE',
            'EXECUTIVE_SECRETARY',
            'HIGH_AUTHORITY',
        ];

        $actualRoles = Role::query()->whereIn('name', $expectedRoles)->pluck('name')->sortBy(fn (string $name): int => array_search($name, $expectedRoles, true))->values()->all();
        $this->assertSame($expectedRoles, $actualRoles);
        $this->assertDatabaseMissing('roles', ['name' => 'MEETING_COORDINATOR', 'guard_name' => 'sanctum']);
        $this->assertDatabaseMissing('user_roles', ['role' => 'MEETING_COORDINATOR']);
        $this->assertDatabaseMissing('model_has_roles', ['role_id' => Role::where('name', 'MEETING_COORDINATOR')->value('id')]);

        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $this->assertTrue($exec->hasScopedRole('EXECUTIVE_MANAGEMENT', $tech->id));
        $this->assertTrue($exec->can('meeting.schedule'));
        $this->assertTrue($exec->can('meeting.create'));
        $this->assertFalse($exec->hasScopedRole('MEETING_COORDINATOR', $tech->id));
    }

    public function test_executive_management_replaces_coordinator_for_own_scope_only(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $director = User::where('email', 'tech.director@example.test')->firstOrFail();
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $employee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id' => $tech->id,
            'subject' => 'آزمون ادغام نقش مدیر اجرائیه',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'participant_ids' => [$employee->id],
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk();

        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-scheduling")->assertForbidden();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-scheduling")->assertForbidden();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-scheduling")->assertOk()->assertJsonPath('data.status', 'SCHEDULING');
    }

    public function test_admin_role_assignment_rejects_deprecated_role(): void
    {
        $systemAdmin = User::where('email', 'system.admin@example.test')->firstOrFail();
        $target = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();

        $this->actingAs($systemAdmin, 'sanctum')
            ->postJson("/api/admin/users/{$target->id}/roles", [
                'role' => 'MEETING_COORDINATOR',
                'organizational_unit_id' => $tech->id,
            ])
            ->assertStatus(422);
    }
}
