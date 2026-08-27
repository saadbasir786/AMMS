<?php

namespace Tests\Feature;

use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterAndMultiDirectorateStaleProposalAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_flow02_superseded_proposal_cannot_be_accepted(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $request = $this->flowRequest('INTER_DIRECTORATE', $tech, $techExec);
        $request->units()->createMany([
            ['organizational_unit_id' => $tech->id, 'role' => 'REQUESTER'],
            ['organizational_unit_id' => $finance->id, 'role' => 'TARGET'],
        ]);

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/schedule-proposals", [
            'proposed_by_unit_id' => $tech->id,
            'meeting_date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => 'تالار اول',
        ])->assertOk();
        $first = MeetingScheduleProposal::where('meeting_request_id', $request->id)->latest('id')->firstOrFail();

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/schedule-proposals", [
            'proposed_by_unit_id' => $tech->id,
            'meeting_date' => now()->addDays(4)->format('Y-m-d'),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'location' => 'تالار دوم',
        ])->assertOk();

        $first->refresh();
        $this->assertSame('SUPERSEDED', $first->status);
        $this->actingAs($financeExec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/schedule-proposals/{$first->id}/accept")
            ->assertStatus(422);
        $this->assertSame('SUPERSEDED', $first->fresh()->status);
    }

    public function test_flow03_superseded_proposal_cannot_receive_new_unit_acceptance(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
        $request = $this->flowRequest('MULTI_DIRECTORATE', $tech, $techExec);
        $request->units()->createMany([
            ['organizational_unit_id' => $tech->id, 'role' => 'REQUESTER'],
            ['organizational_unit_id' => $finance->id, 'role' => 'TARGET'],
            ['organizational_unit_id' => $hr->id, 'role' => 'TARGET'],
        ]);

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/schedule-proposals", [
            'proposed_by_unit_id' => $tech->id,
            'meeting_date' => now()->addDays(3)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => 'تالار اول',
        ])->assertOk();
        $first = MeetingScheduleProposal::where('meeting_request_id', $request->id)->latest('id')->firstOrFail();

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/schedule-proposals", [
            'proposed_by_unit_id' => $tech->id,
            'meeting_date' => now()->addDays(4)->format('Y-m-d'),
            'start_time' => '11:00',
            'end_time' => '12:00',
            'location' => 'تالار دوم',
        ])->assertOk();

        $first->refresh();
        $this->assertSame('SUPERSEDED', $first->status);
        $this->actingAs($financeExec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/multi/schedule-proposals/{$first->id}/accept", ['unit_id' => $finance->id])
            ->assertStatus(422);
        $this->assertSame(0, $first->acceptances()->where('organizational_unit_id', $finance->id)->count());
    }

    private function flowRequest(string $flowType, OrganizationalUnit $requester, User $creator): MeetingRequest
    {
        return MeetingRequest::create([
            'flow_type' => $flowType,
            'requesting_unit_id' => $requester->id,
            'created_by' => $creator->id,
            'subject' => 'ممیزی proposal منسوخ',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'status' => 'SCHEDULING',
            'lead_coordinator_unit_id' => $requester->id,
            'lead_coordinator_user_id' => $creator->id,
        ]);
    }
}
