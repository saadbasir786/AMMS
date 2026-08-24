<?php

namespace Tests\Feature;

use App\Models\AmmsCapabilityScope;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\SpecialCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopedCapabilityAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_system_admin_can_grant_and_revoke_scoped_biometric_capability(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $target = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();

        $grant = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/users/{$target->id}/capabilities", [
            'capability' => SpecialCapability::BIOMETRIC_ENROLLMENT,
            'organizational_unit_id' => $tech->id,
        ]);

        $grant->assertCreated()
            ->assertJsonPath('data.capability', SpecialCapability::BIOMETRIC_ENROLLMENT)
            ->assertJsonPath('data.organizational_unit_id', $tech->id)
            ->assertJsonPath('data.active', true);

        $scope = AmmsCapabilityScope::query()->where('user_id', $target->id)->active()->firstOrFail();
        $this->assertSame($admin->id, (int) $scope->granted_by_user_id);
        $this->assertTrue($target->fresh()->hasDirectPermission('biometric.enroll'));

        $this->actingAs($target, 'sanctum')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.capabilities.0.capability', SpecialCapability::BIOMETRIC_ENROLLMENT);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/admin/users/{$target->id}/capabilities/{$scope->id}")
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $this->assertNotNull($scope->fresh()->revoked_at);
        $this->assertSame($admin->id, (int) $scope->fresh()->revoked_by_user_id);
        $this->assertFalse($target->fresh()->hasDirectPermission('biometric.enroll'));
    }

    public function test_duplicate_grant_is_idempotent(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $target = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $payload = ['capability' => SpecialCapability::BIOMETRIC_ENROLLMENT, 'organizational_unit_id' => $tech->id];

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/users/{$target->id}/capabilities", $payload)->assertCreated();
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/users/{$target->id}/capabilities", $payload)->assertOk();

        $this->assertSame(1, AmmsCapabilityScope::query()
            ->where('user_id', $target->id)
            ->where('capability', SpecialCapability::BIOMETRIC_ENROLLMENT)
            ->where('organizational_unit_id', $tech->id)
            ->active()
            ->count());
    }

    public function test_system_admin_identity_cannot_receive_operational_capability(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/users/{$admin->id}/capabilities", [
            'capability' => SpecialCapability::BIOMETRIC_ENROLLMENT,
            'organizational_unit_id' => $tech->id,
        ])->assertStatus(422);
    }
}
