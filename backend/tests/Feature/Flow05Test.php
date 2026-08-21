<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Flow05Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_multi_high_authority_workflow_completes_with_per_unit_acknowledgements_and_context(): void
    {
        [$request, $users, $units, $employees] = $this->createDraft();
        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/submit")->assertOk()->assertJsonPath('data.status', 'PENDING_REQUESTER_APPROVAL');
        $this->actingAs($users['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/requester-approve")->assertOk()->assertJsonPath('data.status', 'SUBMITTED_TO_PARTICIPATING_DIRECTORATES');
        $this->actingAs($users['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/participating-units/{$units['finance']->id}/review")->assertOk();
        $this->actingAs($users['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/participating-units/{$units['finance']->id}/approve")->assertOk()->assertJsonPath('data.status', 'DIRECTORATES_APPROVED');
        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-office/submit")->assertOk()->assertJsonPath('data.status', 'SUBMITTED_TO_AUTHORITY_OFFICE');
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-office/review")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-office/forward")->assertOk()->assertJsonPath('data.status', 'PENDING_HIGH_AUTHORITY_DECISION');
        $this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority/approve", ['comment' => 'تأیید رسمی'])->assertOk()->assertJsonPath('data.status', 'HIGH_AUTHORITY_APPROVED');
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-scheduling/start")->assertOk();
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-schedule/proposals", ['meeting_date' => now()->addWeek()->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'دفتر مقام'])->assertOk();
        $proposal = \App\Models\MeetingScheduleProposal::where('meeting_request_id', $request->id)->latest('id')->firstOrFail();
        foreach ([['unit' => $units['tech'], 'actor' => $users['techExec']], ['unit' => $units['finance'], 'actor' => $users['financeExec']]] as $pair) $this->actingAs($pair['actor'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-schedule/acknowledge", ['proposal_id' => $proposal->id, 'organizational_unit_id' => $pair['unit']->id])->assertOk();
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-schedule/confirm")->assertOk()->assertJsonPath('data.status', 'SCHEDULE_CONFIRMED');
        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/units/{$units['tech']->id}/participants", ['employee_ids' => [$employees['tech']]])->assertOk();
        $this->actingAs($users['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/units/{$units['tech']->id}/participants/confirm")->assertOk();
        $this->actingAs($users['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/units/{$units['finance']->id}/participants", ['employee_ids' => [$employees['finance']]])->assertOk();
        $this->actingAs($users['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/units/{$units['finance']->id}/participants/confirm")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-participants", ['employee_ids' => [$employees['authority']]])->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-participants/confirm")->assertOk();
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/finalize", ['agenda' => 'آجندا'])->assertOk();
        $meeting = Meeting::where('meeting_request_id', $request->id)->firstOrFail();
        $this->assertSame('CONVERTED_TO_MEETING', $request->fresh()->status);
        $this->assertSame(2, $meeting->units()->count());
        $this->assertSame(1, $meeting->authorityContext()->count());
        $this->assertSame($employees['authority'], $meeting->chairperson_employee_id);
    }

    public function test_flow05_requires_multiple_directorates_and_rejects_cross_scope_actions(): void
    {
        [$request, $users, $units] = $this->createDraft();
        $this->actingAs($users['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/requester-approve")->assertStatus(403);
        $this->actingAs($users['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/participating-units/{$units['tech']->id}/review")->assertStatus(403);
    }

    public function test_schedule_confirmation_requires_every_formal_directorate_acknowledgement(): void
    {
        [$request, $users] = $this->prepareScheduling();
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-schedule/confirm")->assertStatus(422);
    }

    public function test_authority_office_cannot_decide_and_high_authority_cannot_decide_before_forward(): void
    {
        [$request, $users] = $this->prepareOfficeReview();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority/approve")->assertStatus(403);
    }

    public function test_authority_office_cannot_schedule_and_secretary_cannot_manage_authority_participants(): void
    {
        [$request, $users] = $this->prepareOfficeReview();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-office/forward")->assertOk();
        $this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority/approve")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-scheduling/start")->assertStatus(403);
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-scheduling/start")->assertOk();
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-participants", ['employee_ids' => []])->assertStatus(403);
    }

    public function test_executive_management_nominates_but_only_director_confirms_unit_participants(): void
    {
        [$request, $users, $units, $employees] = $this->prepareScheduling();
        $proposal = \App\Models\MeetingScheduleProposal::where('meeting_request_id', $request->id)->latest('id')->firstOrFail();
        foreach ([['unit' => $units['tech'], 'actor' => $users['techExec']], ['unit' => $units['finance'], 'actor' => $users['financeExec']]] as $pair) {
            $this->actingAs($pair['actor'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-schedule/acknowledge", ['proposal_id' => $proposal->id, 'organizational_unit_id' => $pair['unit']->id])->assertOk();
        }
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-schedule/confirm")->assertOk();
        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/units/{$units['tech']->id}/participants", ['employee_ids' => [$employees['tech']]])->assertOk();
        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/units/{$units['tech']->id}/participants/confirm")->assertStatus(403);
        $this->actingAs($users['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/units/{$units['tech']->id}/participants", ['employee_ids' => [$employees['tech']]])->assertStatus(403);
        $this->actingAs($users['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/units/{$units['tech']->id}/participants/confirm")->assertOk();
    }

    private function createDraft(): array
    {
        $u=fn($e)=>User::where('email',$e)->firstOrFail();$unit=fn($c)=>OrganizationalUnit::where('code',$c)->firstOrFail();$emp=fn($n)=>Employee::where('employee_number',$n)->firstOrFail();
        $users=['techExec'=>$u('tech.exec@example.test'),'techDirector'=>$u('tech.director@example.test'),'financeExec'=>$u('finance.exec@example.test'),'financeDirector'=>$u('finance.director@example.test'),'office'=>$u('authority.office@example.test'),'authority'=>$u('high.authority@example.test'),'secretary'=>$u('executive.secretary@example.test')];$units=['tech'=>$unit('TECH'),'finance'=>$unit('FIN')];$employees=['tech'=>$emp('EMP-TECH-003')->id,'finance'=>$emp('EMP-FIN-002')->id,'authority'=>$emp('EMP-AUTH-001')->id];
        $this->actingAs($users['techExec'], 'sanctum')->postJson('/api/meeting-requests/multi-high-authority',['requesting_unit_id'=>$units['tech']->id,'participating_unit_ids'=>[$units['finance']->id],'subject'=>'جلسهٔ چندریاستی آزمایشی','purpose'=>'DECISION','description'=>'شرح','meeting_justification'=>'توجیه','requested_outcome'=>'نتیجه','priority'=>'NORMAL','participant_ids'=>[$employees['tech']]])->assertCreated();
        return [MeetingRequest::latest('id')->firstOrFail(),$users,$units,$employees];
    }

    private function prepareOfficeReview(): array
    {
        [$request,$users,$units] = $this->createDraft();
        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/submit");$this->actingAs($users['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/requester-approve");$this->actingAs($users['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/participating-units/{$units['finance']->id}/review");$this->actingAs($users['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/participating-units/{$units['finance']->id}/approve");$this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-office/submit");$this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-office/review");
        return [$request->fresh(),$users,$units];
    }

    private function prepareScheduling(): array
    {
        [$request,$users,$units] = $this->prepareOfficeReview();$this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-office/forward");$this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority/approve");$this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-scheduling/start");$this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi-high/authority-schedule/proposals",['meeting_date'=>now()->addWeek()->format('Y-m-d'),'start_time'=>'10:00','end_time'=>'11:00','location'=>'دفتر مقام']);return [$request->fresh(),$users,$units, ['tech'=>Employee::where('employee_number','EMP-TECH-003')->firstOrFail()->id,'finance'=>Employee::where('employee_number','EMP-FIN-002')->firstOrFail()->id,'authority'=>Employee::where('employee_number','EMP-AUTH-001')->firstOrFail()->id]];
    }
}
