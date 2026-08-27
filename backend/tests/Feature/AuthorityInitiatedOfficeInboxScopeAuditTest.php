<?php

namespace Tests\Feature;

use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorityInitiatedOfficeInboxScopeAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_system_admin_has_no_flow06_business_inbox_bypass(): void
    {
        $request = $this->createDraft();
        $system = User::where('email', 'system.admin@example.test')->firstOrFail();

        $this->actingAs($system, 'sanctum')
            ->getJson('/api/incoming-authority-initiated-meeting-requests')
            ->assertStatus(403);

        $this->assertDatabaseHas('meeting_requests', ['id' => $request->id, 'flow_type' => 'HIGH_AUTHORITY_INITIATED']);
    }

    public function test_authority_office_and_exact_high_authority_can_open_flow06_inbox(): void
    {
        $this->createDraft();
        $office = User::where('email', 'authority.office@example.test')->firstOrFail();
        $authority = User::where('email', 'high.authority@example.test')->firstOrFail();

        $this->actingAs($office, 'sanctum')
            ->getJson('/api/incoming-authority-initiated-meeting-requests')
            ->assertOk();

        $this->actingAs($authority, 'sanctum')
            ->getJson('/api/incoming-authority-initiated-meeting-requests')
            ->assertOk();
    }

    public function test_directorate_user_cannot_use_authority_office_inbox_even_with_flow06_visibility_later(): void
    {
        $this->createDraft();
        $director = User::where('email', 'tech.director@example.test')->firstOrFail();

        $this->actingAs($director, 'sanctum')
            ->getJson('/api/incoming-authority-initiated-meeting-requests')
            ->assertStatus(403);
    }

    private function createDraft(): MeetingRequest
    {
        $office = User::where('email', 'authority.office@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();

        $this->actingAs($office, 'sanctum')->postJson('/api/meeting-requests/authority-initiated', [
            'subject' => 'Inbox scope audit',
            'purpose' => 'DECISION',
            'directive' => 'دستور مقام برای آزمون scope',
            'requested_outcome' => 'نتیجهٔ قابل سنجش',
            'estimated_duration_minutes' => 60,
            'priority' => 'NORMAL',
            'participating_unit_ids' => [$tech->id],
        ])->assertCreated();

        return MeetingRequest::latest('id')->firstOrFail();
    }
}
