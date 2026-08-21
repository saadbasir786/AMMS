<?php

namespace Tests\Feature;

use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Flow0203NegativeMatrixAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_flow02_target_executive_cannot_start_requester_led_scheduling(): void
    {
        [$request, $techExec, $financeExec] = $this->flow02ToBilateralApproved();

        $this->actingAs($financeExec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/start-inter-directorate-scheduling")
            ->assertForbidden();

        $this->actingAs($techExec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/start-inter-directorate-scheduling")
            ->assertOk()
            ->assertJsonPath('data.status', 'SCHEDULING');
    }

    public function test_flow03_participating_executive_cannot_start_or_finalize_lead_actions(): void
    {
        [$request, $techExec, $financeExec] = $this->flow03ToMultilateralApproved();

        $this->actingAs($financeExec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/start-multi-scheduling")
            ->assertForbidden();

        $this->actingAs($techExec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/start-multi-scheduling")
            ->assertOk()
            ->assertJsonPath('data.status', 'SCHEDULING');
    }

    public function test_historical_flow02_creator_loses_business_access_when_current_scope_is_removed(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();

        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/inter-directorate', [
            'requesting_unit_id' => $tech->id,
            'target_unit_id' => $finance->id,
            'subject' => 'بررسی سلب صلاحیت جاری',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
        ])->assertCreated();

        $request = MeetingRequest::latest('id')->firstOrFail();
        $roleId = $techExec->roles()->where('name', 'EXECUTIVE_MANAGEMENT')->value('id');
        $techExec->roleScopes()->where('role_id', $roleId)->where('organizational_unit_id', $tech->id)->delete();
        $techExec->unsetRelation('roleScopes');

        $this->actingAs($techExec, 'sanctum')
            ->getJson("/api/meeting-requests/{$request->id}")
            ->assertForbidden();

        $this->actingAs($techExec, 'sanctum')
            ->putJson("/api/meeting-requests/{$request->id}", ['description' => 'نباید مجاز باشد'])
            ->assertForbidden();

        $this->actingAs($techExec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/submit-inter-directorate")
            ->assertForbidden();
    }

    private function flow02ToBilateralApproved(): array
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $financeDirector = User::where('email', 'finance.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();

        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/inter-directorate', [
            'requesting_unit_id' => $tech->id,
            'target_unit_id' => $finance->id,
            'subject' => 'ماتریس منفی FLOW-02',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-inter-directorate")->assertOk();
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/requester-approve")->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-review")->assertOk();
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-approve")->assertOk()->assertJsonPath('data.status', 'BILATERAL_APPROVED');

        return [$request->fresh(), $techExec, $financeExec];
    }

    private function flow03ToMultilateralApproved(): array
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $financeDirector = User::where('email', 'finance.director@example.test')->firstOrFail();
        $hrExec = User::where('email', 'hr.exec@example.test')->firstOrFail();
        $hrDirector = User::where('email', 'hr.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();

        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $tech->id,
            'target_unit_ids' => [$finance->id, $hr->id],
            'subject' => 'ماتریس منفی FLOW-03',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-multi")->assertOk();
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/requester-approve")->assertOk();
        foreach ([[$financeExec, $finance, $financeDirector], [$hrExec, $hr, $hrDirector]] as [$exec, $unit, $director]) {
            $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$unit->id}/review")->assertOk();
            $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$unit->id}/approve")->assertOk();
        }
        $request->refresh();
        $this->assertSame('MULTILATERAL_APPROVED', $request->status);

        return [$request, $techExec, $financeExec];
    }
}
