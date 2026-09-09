<?php

namespace Tests\Feature;

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
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.granted_by.id', $director->id);

        $this->actingAs($director, 'sanctum')
            ->getJson('/api/internal-meeting-approval-delegations?status=ACTIVE')
            ->assertOk()
            ->assertJsonPath('data.delegations.0.granted_by.name', $director->name);

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
        $this->assertDatabaseHas('amms_notifications', [
            'user_id' => $executive->id,
            'type' => 'DELEGATION_GRANTED',
        ]);

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

        $this->assertDatabaseHas('amms_notifications', [
            'user_id' => $executive->id,
            'type' => 'DELEGATION_REVOKED',
        ]);

        $request = $this->createAndSubmitInternalRequest($executive, $tech);
        $this->actingAs($executive, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/approve")
            ->assertStatus(403);

        $this->assertDatabaseHas('amms_capability_scopes', [
            'id' => $scopeId,
            'revocation_reason' => 'پایان ماموریت.',
        ]);
    }

    public function test_delegation_index_exposes_stable_pagination_and_authoritative_stats(): void
    {
        [$director, $executive, $tech] = $this->technologyActors();

        $this->actingAs($director, 'sanctum')
            ->postJson('/api/internal-meeting-approval-delegations', [
                'organizational_unit_id' => $tech->id,
                'delegate_user_id' => $executive->id,
                'expires_at' => now()->addWeek()->toDateString(),
                'reason' => 'تست استانداردسازی فهرست تفویض.',
            ])
            ->assertCreated();

        $this->actingAs($director, 'sanctum')
            ->getJson('/api/internal-meeting-approval-delegations?per_page=1&page=1&status=ACTIVE')
            ->assertOk()
            ->assertJsonPath('data.delegations_meta.per_page', 1)
            ->assertJsonPath('data.delegations_meta.current_page', 1)
            ->assertJsonPath('data.stats.total', 1)
            ->assertJsonPath('data.stats.active', 1)
            ->assertJsonPath('data.stats.revoked', 0)
            ->assertJsonPath('data.stats.expired', 0);

        $this->actingAs($director, 'sanctum')
            ->getJson('/api/internal-meeting-approval-delegations?per_page=0')
            ->assertStatus(422);
    }

    public function test_director_can_delegate_internal_approval_to_deputy_director_and_deputy_can_approve(): void
    {
        [$director, $executive, $tech] = $this->technologyActors();
        $deputy = User::query()->where('email', 'tech.deputy@example.test')->firstOrFail();

        // 1. Verify candidate list exposes both executive and deputy with their role titles
        $indexResponse = $this->actingAs($director, 'sanctum')
            ->getJson('/api/internal-meeting-approval-delegations')
            ->assertOk();

        $candidateIds = collect($indexResponse->json('data.delegates'))->pluck('id')->all();
        $this->assertContains($executive->id, $candidateIds);
        $this->assertContains($deputy->id, $candidateIds);

        $deputyEntry = collect($indexResponse->json('data.delegates'))->firstWhere('id', $deputy->id);
        $this->assertEquals('معاون ریاست', $deputyEntry['role_title']);

        $execEntry = collect($indexResponse->json('data.delegates'))->firstWhere('id', $executive->id);
        $this->assertEquals('مدیر اجرائیه', $execEntry['role_title']);

        // 2. Delegate to Deputy Director
        $this->actingAs($director, 'sanctum')
            ->postJson('/api/internal-meeting-approval-delegations', [
                'organizational_unit_id' => $tech->id,
                'delegate_user_id' => $deputy->id,
                'expires_at' => now()->addWeek()->toDateString(),
                'reason' => 'تفویض صلاحیت تأیید به معاون محترم ریاست.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.capability', SpecialCapability::INTERNAL_MEETING_REQUEST_APPROVAL)
            ->assertJsonPath('data.active', true);

        $this->assertTrue($deputy->fresh()->can('meeting-request.approve-delegated'));
        $this->assertTrue($deputy->fresh()->can('meeting-request.view'));

        // 3. Deputy can approve internal meeting request
        $request = $this->createAndSubmitInternalRequest($executive, $tech);

        $this->actingAs($deputy->fresh(), 'sanctum')
            ->getJson("/api/meeting-requests/{$request->id}/action-capabilities")
            ->assertOk()
            ->assertJsonPath('data.approve', true)
            ->assertJsonPath('data.approve_with_delegation', true);

        $this->actingAs($deputy->fresh(), 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');

        $this->assertDatabaseHas('meeting_request_actions', [
            'meeting_request_id' => $request->id,
            'actor_id' => $deputy->id,
            'action' => 'APPROVED_UNDER_DELEGATION',
        ]);
    }

    public function test_delegation_cannot_be_granted_to_regular_employee(): void
    {
        [$director, , $tech] = $this->technologyActors();
        $employee = Employee::create([
            'employee_number' => 'EMP-REG-'.uniqid(),
            'first_name' => 'کارمند',
            'last_name' => 'عادی',
            'email' => 'tech.employee.'.uniqid().'@example.test',
            'organizational_unit_id' => $tech->id,
            'status' => 'ACTIVE',
        ]);
        $employeeUser = User::create([
            'name' => 'کارمند عادی تکنالوژی',
            'email' => $employee->email,
            'password' => \Illuminate\Support\Facades\Hash::make('ChangeMe!2026'),
            'employee_id' => $employee->id,
            'status' => 'ACTIVE',
        ]);
        $employeeUser->assignRole('EMPLOYEE');

        $this->actingAs($director, 'sanctum')
            ->postJson('/api/internal-meeting-approval-delegations', [
                'organizational_unit_id' => $tech->id,
                'delegate_user_id' => $employeeUser->id,
                'expires_at' => now()->addWeek()->toDateString(),
                'reason' => 'تلاش نامعتبر برای تفویض به کارمند عادی.',
            ])
            ->assertStatus(422);
    }

    public function test_duplicate_delegation_to_same_user_is_prevented(): void
    {
        [$director, $executive, $tech] = $this->technologyActors();

        // 1. Initial successful delegation
        $this->actingAs($director, 'sanctum')
            ->postJson('/api/internal-meeting-approval-delegations', [
                'organizational_unit_id' => $tech->id,
                'delegate_user_id' => $executive->id,
                'expires_at' => now()->addWeek()->toDateString(),
                'reason' => 'تفویض اول به مدیر اجرائیه.',
            ])
            ->assertCreated();

        // 2. Candidate list exposes has_active_delegation = true
        $indexResponse = $this->actingAs($director, 'sanctum')
            ->getJson('/api/internal-meeting-approval-delegations')
            ->assertOk();

        $candidate = collect($indexResponse->json('data.delegates'))->firstWhere('id', $executive->id);
        $this->assertNotNull($candidate);
        $this->assertTrue($candidate['has_active_delegation']);

        // 3. Attempting to delegate AGAIN to the same executive manager must fail with 422
        $this->actingAs($director, 'sanctum')
            ->postJson('/api/internal-meeting-approval-delegations', [
                'organizational_unit_id' => $tech->id,
                'delegate_user_id' => $executive->id,
                'expires_at' => now()->addWeeks(2)->toDateString(),
                'reason' => 'تلاش اشتباه برای تفویض مجدد.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'این کاربر در حال حاضر دارای تفویض صلاحیت فعال برای تأیید جلسات داخلی در این اداره می‌باشد.');
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
