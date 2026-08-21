<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FoundationToFlow06FinalRegressionAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_primary_role_model_has_no_legacy_meeting_coordinator(): void
    {
        foreach ([
            'SYSTEM_ADMIN',
            'EMPLOYEE',
            'DIRECTOR',
            'EXECUTIVE_MANAGEMENT',
            'AUTHORITY_OFFICE',
            'EXECUTIVE_SECRETARY',
            'HIGH_AUTHORITY',
        ] as $role) {
            $this->assertTrue(Role::query()->where('name', $role)->where('guard_name', 'sanctum')->exists(), "Missing role {$role}");
        }

        $this->assertFalse(Role::query()->where('name', 'MEETING_COORDINATOR')->exists());
    }

    public function test_meeting_creation_has_no_direct_post_meetings_route(): void
    {
        $directMeetingCreate = collect(Route::getRoutes()->getRoutes())->contains(function ($route): bool {
            return in_array('POST', $route->methods(), true) && trim($route->uri(), '/') === 'api/meetings';
        });

        $this->assertFalse($directMeetingCreate, 'Official Meeting must be created only through MeetingRequest conversion/finalization flows.');
    }

    public function test_system_admin_cannot_create_business_meeting_requests_in_flows_01_to_06(): void
    {
        $system = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $tech = \App\Models\OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $finance = \App\Models\OrganizationalUnit::query()->where('code', 'FIN')->firstOrFail();
        $hr = \App\Models\OrganizationalUnit::query()->where('code', 'HR')->firstOrFail();

        $common = [
            'subject' => 'Business authority boundary audit',
            'purpose' => 'DECISION',
            'priority' => 'NORMAL',
        ];

        $this->actingAs($system, 'sanctum')->postJson('/api/meeting-requests', $common + [
            'requesting_unit_id' => $tech->id,
        ])->assertStatus(403);

        $this->actingAs($system, 'sanctum')->postJson('/api/meeting-requests/inter-directorate', $common + [
            'requesting_unit_id' => $tech->id,
            'target_unit_id' => $finance->id,
        ])->assertStatus(403);

        $this->actingAs($system, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', $common + [
            'requesting_unit_id' => $tech->id,
            'target_unit_ids' => [$finance->id, $hr->id],
        ])->assertStatus(403);

        $this->actingAs($system, 'sanctum')->postJson('/api/meeting-requests/high-authority', $common + [
            'requesting_unit_id' => $tech->id,
            'meeting_justification' => 'Audit',
            'requested_outcome' => 'Audit',
        ])->assertStatus(403);

        $this->actingAs($system, 'sanctum')->postJson('/api/meeting-requests/multi-high-authority', $common + [
            'requesting_unit_id' => $tech->id,
            'participating_unit_ids' => [$finance->id],
        ])->assertStatus(403);

        $this->actingAs($system, 'sanctum')->postJson('/api/meeting-requests/authority-initiated', $common + [
            'directive' => 'Audit directive',
            'requested_outcome' => 'Audit',
            'participating_unit_ids' => [$tech->id],
        ])->assertStatus(403);
    }
}
