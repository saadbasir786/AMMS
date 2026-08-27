<?php

namespace Tests\Feature;

use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiDirectorateLeadAuthorityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_participating_executive_cannot_start_lead_scheduling_transition(): void
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
            'subject' => 'بررسی مرز صلاحیت لید FLOW-03',
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

        $this->actingAs($financeExec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/start-multi-scheduling")
            ->assertForbidden();

        $this->actingAs($techExec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/start-multi-scheduling")
            ->assertOk()
            ->assertJsonPath('data.status', 'SCHEDULING');
    }
}
