<?php

namespace Tests\Feature;

use App\Models\AmmsCapabilityScope;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\SpecialCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalMeetingApprovalDelegationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_director_can_delegate_and_executive_manager_can_approve_only_internal_requests(): void
    {
        [$director, $executive, $tech] = $this->technologyActors();

        $this->actingAs($director, 'sanctum')
            ->postJson('/api/internal-meeting-approval-delegations', [
                'organizational_unit_id' => $tech->id,
                'delegate_user_id' => $executive->id,
                'expires_at' => now()->addWeek()->toDateString(),
                'reason' => 'ماموریت رسمی رئیس ریاست.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.capability', SpecialCapability::INTERNAL_MEETING_REQUEST_APPROVAL)
            ->assertJsonPath('data.active', true);

        $this->assertTrue($executive->fresh()->can('meeting-request.approve-delegated'));

        $request = $this->createAndSubmitInternalRequest($executive, $tech);

        $this->actingAs($executive, 'sanctum')
            ->getJson("/api/meeting-requests/{$request->id}/action-capabilities")
            ->assertOk()
            ->assertJsonPath('data.approve', true)
            ->assertJsonPath('data.approve_with_delegation', true)
            ->assertJsonPath('data.return_for_revision', false)
            ->assertJsonPath('data.reject', false);

        $this->actingAs($executive, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertDatabaseHas('meeting_request_actions', [
            'meeting_request_id' => $request->id,
            'actor_id' => $executive->id,
            'action' => 'APPROVED_UNDER_DELEGATION',
        ]);
    }

    public function test_delegation_is_limited_to_same_unit_and_can_be_revoked(): void
    {
        [$director, $executive, $tech] = $this->technologyActors();
        $finance = OrganizationalUnit::query()->where('code', 'FIN')->firstOrFail();

        $grant = $this->actingAs($director, 'sanctum')
            ->postJson('/api/internal-meeting-approval-delegations', [
                'organizational_unit_id' => $tech->id,
                'delegate_user_id' => $executive->id,
                'expires_at' => now()->addWeek()->toDateString(),
                'reason' => 'تفویض موقت.',
            ])
            ->assertCreated();

        $scopeId = $grant->json('data.id');
        $this->actingAs($director, 'sanctum')
            ->postJson('/api/internal-meeting-approval-delegations', [
                'organizational_unit_id' => $finance->id,
                'delegate_user_id' => $executive->id,
                'expires_at' => now()->addWeek()->toDateString(),
                'reason' => 'نباید قابل ثبت باشد.',
            ])
            ->assertStatus(403);

        $this->actingAs($director, 'sanctum')
            ->deleteJson("/api/internal-meeting-approval-delegations/{$scopeId}", ['reason' => 'پایان ماموریت.'])
            ->assertOk()
            ->assertJsonPath('data.active', false);

        $request = $this->createAndSubmitInternalRequest($executive, $tech);
        $this->actingAs($executive, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/approve")
            ->assertStatus(403);

        $this->assertDatabaseHas('amms_capability_scopes', [
            'id' => $scopeId,
            'revocation_reason' => 'پایان ماموریت.',
        ]);
    }

    /** @return array{User, User, OrganizationalUnit} */
    private function technologyActors(): array
    {
        return [
            User::query()->where('email', 'tech.director@example.test')->firstOrFail(),
            User::query()->where('email', 'tech.exec@example.test')->firstOrFail(),
            OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail(),
        ];
    }

    private function createAndSubmitInternalRequest(User $executive, OrganizationalUnit $unit): MeetingRequest
    {
        $participant = Employee::query()->where('employee_number', 'EMP-TECH-003')->firstOrFail();

        $this->actingAs($executive, 'sanctum')
            ->postJson('/api/meeting-requests', [
                'requesting_unit_id' => $unit->id,
                'subject' => 'جلسه داخلی با تأیید تفویض‌شده',
                'purpose' => 'COORDINATION',
                'priority' => 'NORMAL',
                'participant_ids' => [$participant->id],
            ])
            ->assertCreated();

        $request = MeetingRequest::query()->latest('id')->firstOrFail();
        $this->actingAs($executive, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'PENDING_INTERNAL_APPROVAL');

        return $request;
    }
}
