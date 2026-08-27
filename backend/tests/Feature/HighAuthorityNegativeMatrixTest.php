<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HighAuthorityNegativeMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_a_unrelated_finance_director_cannot_view_or_approve_technology_request(): void
    {
        [$request] = $this->submittedToRequesterApproval();
        $finance = User::where('email', 'finance.director@example.test')->firstOrFail();
        $this->actingAs($finance, 'sanctum')->getJson("/api/meeting-requests/{$request->id}")->assertStatus(403);
        $this->actingAs($finance, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/high-authority/requester-approve")->assertStatus(403);
    }

    public function test_b_authority_office_cannot_make_high_authority_decision(): void
    {
        [$request] = $this->submittedToOffice();
        $office = User::where('email', 'authority.office@example.test')->firstOrFail();
        $this->actingAs($office, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority/approve")->assertStatus(403);
    }

    public function test_c_high_authority_cannot_decide_before_office_forward(): void
    {
        [$request] = $this->submittedToOffice();
        $authority = User::where('email', 'high.authority@example.test')->firstOrFail();
        $this->actingAs($authority, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority/approve")->assertStatus(403);
    }

    public function test_d_scheduler_cannot_start_before_high_authority_approval(): void
    {
        [$request] = $this->submittedToOffice();
        $secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
        $this->actingAs($secretary, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-scheduling/start")->assertStatus(422);
    }

    public function test_e_requester_cannot_manage_authority_side_participants(): void
    {
        [$request] = $this->authorityApprovedAndScheduling();
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $authorityEmployee = Employee::where('employee_number', 'EMP-AUTH-001')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-participants", ['employee_ids' => [$authorityEmployee->id]])->assertStatus(403);
    }

    public function test_f_multi_directorate_payload_is_rejected_by_flow04_boundary(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $employee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $response = $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/high-authority', [
            'requesting_unit_id' => [$tech->id, $finance->id],
            'subject' => 'درخواست چندریاستی با مقام عالی',
            'purpose' => 'DECISION',
            'meeting_justification' => 'توجیه آزمون مرز FLOW-04.',
            'requested_outcome' => 'نتیجهٔ آزمون.',
            'priority' => 'NORMAL',
            'participant_ids' => [$employee->id],
        ]);
        $this->assertGreaterThanOrEqual(400, $response->status());
    }

    public function test_g_duplicate_finalization_creates_exactly_one_meeting(): void
    {
        [$request, $users] = $this->completeWorkflow();
        $second = $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize-high-authority", ['agenda' => 'تلاش تکراری']);
        $this->assertGreaterThanOrEqual(400, $second->status());
        $this->assertSame(1, Meeting::where('meeting_request_id', $request->id)->count());
    }

    private function submittedToRequesterApproval(): array
    {
        $users = $this->users();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $employee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $this->actingAs($users['techExec'], 'sanctum')->postJson('/api/meeting-requests/high-authority', ['requesting_unit_id'=>$tech->id,'subject'=>'آزمون منفی','purpose'=>'DECISION','meeting_justification'=>'توجیه','requested_outcome'=>'نتیجه','priority'=>'NORMAL','participant_ids'=>[$employee->id]])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-high-authority")->assertOk();
        return [$request, $users];
    }

    private function submittedToOffice(): array
    {
        [$request, $users] = $this->submittedToRequesterApproval();
        $this->actingAs($users['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/high-authority/requester-approve")->assertOk();
        return [$request->fresh(), $users];
    }

    private function authorityApprovedAndScheduling(): array
    {
        [$request, $users] = $this->submittedToOffice();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/review")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/forward")->assertOk();
        $this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority/approve")->assertOk();
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-scheduling/start")->assertOk();
        return [$request->fresh(), $users];
    }

    private function completeWorkflow(): array
    {
        [$request, $users] = $this->authorityApprovedAndScheduling();
        $date = now()->addWeek()->format('Y-m-d');
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-schedule/proposals", ['meeting_date'=>$date,'start_time'=>'10:00','end_time'=>'11:00','location'=>'دفتر مقام'])->assertOk();
        $proposal = MeetingScheduleProposal::where('meeting_request_id', $request->id)->latest('id')->firstOrFail();
        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-schedule/proposals/{$proposal->id}/acknowledge")->assertOk();
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-schedule/proposals/{$proposal->id}/confirm")->assertOk();
        $techEmployee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $authorityEmployee = Employee::where('employee_number', 'EMP-AUTH-001')->firstOrFail();
        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/high-authority/requester-participants", ['employee_ids'=>[$techEmployee->id]])->assertOk();
        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/high-authority/requester-participants/confirm")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-participants", ['employee_ids'=>[$authorityEmployee->id]])->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-participants/confirm")->assertOk();
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize-high-authority", ['agenda'=>'آجندای آزمون'])->assertOk();
        return [$request->fresh(), $users];
    }

    private function users(): array
    {
        return [
            'techExec'=>User::where('email','tech.exec@example.test')->firstOrFail(),
            'techDirector'=>User::where('email','tech.director@example.test')->firstOrFail(),
            'office'=>User::where('email','authority.office@example.test')->firstOrFail(),
            'secretary'=>User::where('email','executive.secretary@example.test')->firstOrFail(),
            'authority'=>User::where('email','high.authority@example.test')->firstOrFail(),
        ];
    }
}
