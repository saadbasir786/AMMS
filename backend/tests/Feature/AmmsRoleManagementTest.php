<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AmmsRoleAudit;
use App\Models\AmmsRoleDefinition;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\AmmsRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class AmmsRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_system_roles_are_read_only_and_custom_roles_are_audited(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/roles')->assertOk()->assertJsonPath('data.0.role_kind', 'SYSTEM');

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/roles', [
            'name' => 'REPORT_VIEWER',
            'label' => 'بازرس گزارش‌ها',
            'description' => 'دسترسی گزارش‌دهی محدود به حوزه کاربر.',
        ])->assertCreated()->assertJsonPath('data.name', 'REPORT_VIEWER');

        $role = Role::findByName('REPORT_VIEWER', 'sanctum');
        $this->assertDatabaseHas('amms_role_audits', ['role_id' => $role->id, 'action' => 'CUSTOM_ROLE_CREATED']);
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/roles/'.$role->id.'/audits')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'CUSTOM_ROLE_CREATED')
            ->assertJsonPath('data.0.role_id', $role->id);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/roles', [
            'name' => 'REPORT_VIEWER',
            'label' => 'Duplicate',
        ])->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/roles', [
            'name' => AmmsRole::DIRECTOR,
            'label' => 'Reserved',
        ])->assertStatus(422);

        $system = Role::findByName(AmmsRole::DIRECTOR, 'sanctum');
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/roles/'.$system->id, [
            'name' => AmmsRole::DIRECTOR,
            'label' => 'تغییر غیرمجاز',
            'description' => null,
        ])->assertStatus(422);
    }

    public function test_custom_role_permission_whitelist_and_active_scope_are_enforced(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $employee = User::query()->where('email', 'employee.a@example.test')->firstOrFail();
        $unit = OrganizationalUnit::query()->where('code', 'TECH-SW')->firstOrFail();

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/roles', ['name' => 'REPORT_VIEWER', 'label' => 'بازرس گزارش‌ها'])->assertCreated();
        $role = Role::findByName('REPORT_VIEWER', 'sanctum');

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/roles/'.$role->id.'/permissions', ['permissions' => ['reporting.requests.view']])->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/roles/'.$role->id.'/permissions', ['permissions' => ['meeting-request.approve']])->assertStatus(422);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users/'.$employee->id.'/roles', [
            'role' => 'REPORT_VIEWER',
            'organizational_unit_id' => $unit->id,
        ])->assertCreated();

        $employee->refresh();
        $this->assertTrue($employee->hasPermissionTo('reporting.requests.view', 'sanctum'));
        $this->assertTrue($employee->hasScopedPermission('reporting.requests.view', $unit->id));
        $otherUnit = OrganizationalUnit::query()->where('id', '<>', $unit->id)->firstOrFail();
        $this->assertFalse($employee->hasScopedPermission('reporting.requests.view', $otherUnit->id));
        $this->assertFalse($employee->hasScopedRole('REPORT_VIEWER', $unit->id));
        $this->assertDatabaseHas('user_access_audits', ['user_id' => $employee->id, 'action' => 'CUSTOM_ROLE_ASSIGNED']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/roles/'.$role->id.'/deactivate')->assertOk();
        $employee->refresh();
        $this->assertFalse($employee->hasPermissionTo('reporting.requests.view', 'sanctum'));
        $this->assertFalse($employee->hasScopedPermission('reporting.requests.view', $unit->id));
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/users/'.$employee->id.'/roles', [
            'role' => 'REPORT_VIEWER',
            'organizational_unit_id' => $unit->id,
        ])->assertStatus(422);
        $this->assertTrue(AmmsRoleAudit::query()->where('role_id', $role->id)->where('action', 'CUSTOM_ROLE_DEACTIVATED')->exists());
        $this->assertSame(AmmsRoleDefinition::INACTIVE, AmmsRoleDefinition::query()->where('role_id', $role->id)->value('status'));
    }

    public function test_non_admin_cannot_manage_roles_and_canonical_identity_rejects_custom_name(): void
    {
        $employee = User::query()->where('email', 'employee.a@example.test')->firstOrFail();

        $this->getJson('/api/admin/roles')->assertUnauthorized();
        $this->actingAs($employee, 'sanctum')->getJson('/api/admin/roles')->assertForbidden();
        $this->assertFalse($employee->hasScopedRole('REPORT_VIEWER'));
    }
}
