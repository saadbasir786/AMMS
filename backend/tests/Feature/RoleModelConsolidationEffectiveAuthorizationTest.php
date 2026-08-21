<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleModelConsolidationEffectiveAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_seed_has_one_technology_executive_manager_and_no_coordinator_authority(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $admin = User::where('email', 'system.admin@example.test')->firstOrFail();

        $this->assertTrue($exec->hasScopedRole('EXECUTIVE_MANAGEMENT', $tech->id));
        $this->assertSame(1, User::query()->whereHas('roles', fn ($query) => $query->where('name', 'EXECUTIVE_MANAGEMENT'))
            ->whereHas('roleScopes', fn ($query) => $query->where('organizational_unit_id', $tech->id))
            ->count());
        $this->assertDatabaseMissing('users', ['email' => 'tech.coordinator@example.test']);
        $this->assertFalse($admin->hasScopedRole('EXECUTIVE_MANAGEMENT', $tech->id));
        $this->assertFalse($exec->hasScopedRole('MEETING_COORDINATOR', $tech->id));
    }

    public function test_effective_authority_boundaries_use_real_http_authorization(): void
    {
        [$users, $units, $employees] = $this->fixture();
        $request = $this->prepareHighAuthorityDecision($users, $units, $employees);

        // EXECUTIVE_MANAGEMENT retains own Directorate coordination, but not HA decision,
        // Executive Secretary scheduling, or Authority Office participant ownership.
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority/approve")->assertForbidden();
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-scheduling/start")->assertForbidden();
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-participants", ['employee_ids' => [$employees['authority']]])->assertForbidden();

        // The exact High Authority actor is still the only decision actor.
        $this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority/approve")->assertOk();

        // Executive Secretary retains its frozen scheduling authority.
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-scheduling/start")->assertOk();

        // Authority Office retains authority-participant ownership after scheduling starts.
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-participants", ['employee_ids' => [$employees['authority']]])->assertOk();

        // FLOW-06 formal issue remains High Authority-only; Executive Management cannot issue,
        // administer Authority participants, or finalize the authority-initiated request.
        $this->actingAs($users['office'], 'sanctum')->postJson('/api/meeting-requests/authority-initiated', [
            'subject' => 'دستور آزمون مرز صلاحیت',
            'purpose' => 'DECISION',
            'directive' => 'متن دستور',
            'requested_outcome' => 'نتیجه',
            'priority' => 'HIGH',
            'participating_unit_ids' => [$units['tech']->id],
        ])->assertCreated();
        $authorityInitiated = MeetingRequest::latest('id')->firstOrFail();

        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$authorityInitiated->id}/authority-initiated/issue")->assertForbidden();
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$authorityInitiated->id}/authority-initiated/authority-participants", ['employee_ids' => [$employees['authority']]])->assertForbidden();
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$authorityInitiated->id}/authority-initiated/finalize", ['agenda' => 'آجندا'])->assertForbidden();

        // Director scheduling denial must be proved on a valid FLOW-01 resource; otherwise a
        // cross-flow endpoint mismatch can mask the role boundary we are trying to test.
        $this->actingAs($users['exec'], 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id' => $units['tech']->id,
            'subject' => 'آزمون مرز زمان‌بندی ریاست',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'participant_ids' => [$employees['tech']],
        ])->assertCreated();
        $flow01 = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$flow01->id}/submit")->assertOk();
        $this->actingAs($users['director'], 'sanctum')->postJson("/api/meeting-requests/{$flow01->id}/approve")->assertOk();
        $this->actingAs($users['director'], 'sanctum')->postJson("/api/meeting-requests/{$flow01->id}/start-scheduling")->assertForbidden();
    }

    private function prepareHighAuthorityDecision(array $users, array $units, array $employees): MeetingRequest
    {
        $this->actingAs($users['exec'], 'sanctum')->postJson('/api/meeting-requests/high-authority', [
            'requesting_unit_id' => $units['tech']->id,
            'subject' => 'درخواست آزمون صلاحیت مؤثر',
            'purpose' => 'DECISION',
            'meeting_justification' => 'توجیه آزمون',
            'requested_outcome' => 'نتیجهٔ قابل سنجش',
            'priority' => 'HIGH',
            'participant_ids' => [$employees['tech']],
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();

        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-high-authority")->assertOk();
        $this->actingAs($users['director'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/high-authority/requester-approve")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/review")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/forward")->assertOk();

        return $request->fresh();
    }

    private function fixture(): array
    {
        $user = fn (string $email) => User::where('email', $email)->firstOrFail();
        $unit = fn (string $code) => OrganizationalUnit::where('code', $code)->firstOrFail();
        $employee = fn (string $number) => Employee::where('employee_number', $number)->firstOrFail();

        return [
            [
                'exec' => $user('tech.exec@example.test'),
                'director' => $user('tech.director@example.test'),
                'office' => $user('authority.office@example.test'),
                'secretary' => $user('executive.secretary@example.test'),
                'authority' => $user('high.authority@example.test'),
            ],
            [
                'tech' => $unit('TECH'),
            ],
            [
                'tech' => $employee('EMP-TECH-003')->id,
                'authority' => $employee('EMP-AUTH-001')->id,
            ],
        ];
    }
}
