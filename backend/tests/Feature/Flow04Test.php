<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Flow04Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_high_authority_workflow_completes_with_explicit_decision_schedule_and_context(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $office = User::where('email', 'authority.office@example.test')->firstOrFail();
        $secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
        $authority = User::where('email', 'high.authority@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $techEmployee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $authorityEmployee = Employee::where('employee_number', 'EMP-AUTH-001')->firstOrFail();

        $created = $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/high-authority', [
            'requesting_unit_id' => $tech->id,
            'subject' => 'جلسه با مقام عالی برای تحول دیجیتال',
            'purpose' => 'DECISION',
            'description' => 'بررسی مرحلهٔ بعدی تحول دیجیتال.',
            'meeting_justification' => 'برای تصویب اولویت‌های اجرایی و رفع موانع بین‌ریاستی.',
            'requested_outcome' => 'تصمیم رسمی دربارهٔ اولویت‌ها و مسئولیت‌های اجرایی.',
            'priority' => 'HIGH',
            'participant_ids' => [$techEmployee->id],
        ]);
        $created->assertCreated()->assertJsonPath('data.flow_type', 'DIRECTORATE_WITH_HIGH_AUTHORITY')->assertJsonPath('data.status', 'DRAFT');
        $request = MeetingRequest::latest('id')->firstOrFail();

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-high-authority")->assertOk()->assertJsonPath('data.status', 'PENDING_REQUESTER_APPROVAL');
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/high-authority/requester-approve")->assertOk()->assertJsonPath('data.status', 'SUBMITTED_TO_AUTHORITY_OFFICE');
        $this->actingAs($office, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/review")->assertOk()->assertJsonPath('data.status', 'UNDER_AUTHORITY_OFFICE_REVIEW');
        $this->actingAs($office, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/forward")->assertOk()->assertJsonPath('data.status', 'PENDING_HIGH_AUTHORITY_DECISION');
        $this->actingAs($authority, 'sanctum')->getJson('/api/incoming-high-authority-decisions')->assertOk()->assertJsonPath('data.0.id', $request->id);
        $this->actingAs($authority, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority/approve", ['comment' => 'با توجه به justification، تأیید شد.'])->assertOk()->assertJsonPath('data.status', 'HIGH_AUTHORITY_APPROVED');

        $this->actingAs($secretary, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-scheduling/start")->assertOk()->assertJsonPath('data.status', 'AUTHORITY_SCHEDULING');
        $proposalResponse = $this->actingAs($secretary, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-schedule/proposals", [
            'meeting_date' => now()->addWeek()->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '10:00', 'location' => 'دفتر مقام', 'comment' => 'پیشنهاد رسمی دفتر مقام.',
        ])->assertOk();
        $proposal = \App\Models\MeetingScheduleProposal::where('meeting_request_id', $request->id)->latest('id')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-schedule/proposals/{$proposal->id}/acknowledge")->assertOk();
        $this->actingAs($secretary, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-schedule/proposals/{$proposal->id}/confirm")->assertOk()->assertJsonPath('data.status', 'SCHEDULE_CONFIRMED');

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/high-authority/requester-participants", ['employee_ids' => [$techEmployee->id]])->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/high-authority/requester-participants/confirm")->assertOk();
        $this->actingAs($office, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-participants", ['employee_ids' => [$authorityEmployee->id]])->assertOk();
        $this->actingAs($office, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-participants/confirm")->assertOk();

        $final = $this->actingAs($secretary, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize-high-authority", ['agenda' => 'تصمیم‌گیری دربارهٔ پلان اجرایی.']);
        $final->assertOk()->assertJsonPath('data.authority_context.authority_employee_id', $authorityEmployee->id);
        $this->assertSame('CONVERTED_TO_MEETING', $request->fresh()->status);
        $meeting = Meeting::where('meeting_request_id', $request->id)->firstOrFail();
        $this->assertSame(1, $meeting->units()->count());
        $this->assertSame($authorityEmployee->id, $meeting->chairperson_employee_id);
        $this->assertDatabaseHas('meeting_authority_contexts', ['meeting_id' => $meeting->id, 'authority_employee_id' => $authorityEmployee->id]);
        $this->assertSame(2, $meeting->participants()->count());
    }

    public function test_wrong_director_cannot_decide_and_office_cannot_decide(): void
    {
        [$request] = $this->prepareDecision();
        $financeDirector = User::where('email', 'finance.director@example.test')->firstOrFail();
        $office = User::where('email', 'authority.office@example.test')->firstOrFail();
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority/approve")->assertStatus(403);
        $this->actingAs($office, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority/approve")->assertStatus(403);
    }

    public function test_office_return_requires_reason_and_non_material_resubmit_preserves_requester_approval(): void
    {
        [$request] = $this->prepareOfficeReview();
        $office = User::where('email', 'authority.office@example.test')->firstOrFail();
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $this->actingAs($office, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/return", [])->assertStatus(422);
        $this->actingAs($office, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/return", ['reason' => 'لطفاً سند پشتیبان اضافه شود.'])->assertOk()->assertJsonPath('data.status', 'RETURNED_BY_AUTHORITY_OFFICE');
        $approvedAt = $request->fresh()->requester_approved_at;
        $this->actingAs($techExec, 'sanctum')->putJson("/api/meeting-requests/{$request->id}/high-authority", ['description' => 'شرح تکمیل‌شده بدون تغییر موضوع.'])->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/resubmit-high-authority")->assertOk()->assertJsonPath('data.status', 'SUBMITTED_TO_AUTHORITY_OFFICE');
        $this->assertEquals($approvedAt, $request->fresh()->requester_approved_at);
    }

    public function test_material_change_after_high_authority_return_requires_requester_reapproval(): void
    {
        [$request] = $this->prepareDecision();
        $authority = User::where('email', 'high.authority@example.test')->firstOrFail();
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $this->actingAs($authority, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority/return", ['reason' => 'نتیجهٔ مورد انتظار باید دقیق‌تر شود.'])->assertOk()->assertJsonPath('data.status', 'RETURNED_BY_HIGH_AUTHORITY');
        $this->actingAs($techExec, 'sanctum')->putJson("/api/meeting-requests/{$request->id}/high-authority", ['requested_outcome' => 'تصمیم مشخص و قابل سنجش دربارهٔ پلان اجرایی.'])->assertOk()->assertJsonPath('data.status', 'PENDING_REQUESTER_APPROVAL');
        $this->assertNull($request->fresh()->requester_approved_at);
    }

    public function test_schedule_cannot_confirm_without_requester_acknowledgement(): void
    {
        [$request] = $this->prepareDecision();
        $authority = User::where('email', 'high.authority@example.test')->firstOrFail();
        $secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
        $this->actingAs($authority, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority/approve")->assertOk();
        $this->actingAs($secretary, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-scheduling/start")->assertOk();
        $this->actingAs($secretary, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-schedule/proposals", ['meeting_date' => now()->addWeek()->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '10:00', 'location' => 'دفتر مقام'])->assertOk();
        $proposal = \App\Models\MeetingScheduleProposal::where('meeting_request_id', $request->id)->latest('id')->firstOrFail();
        $this->actingAs($secretary, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-schedule/proposals/{$proposal->id}/confirm")->assertStatus(422)->assertJsonPath('code', 'AUTHORITY_SCHEDULE_NOT_ACKNOWLEDGED');
    }

    public function test_participant_ownership_is_separated_between_requester_and_authority_office(): void
    {
        [$request] = $this->prepareDecision();
        $authority = User::where('email', 'high.authority@example.test')->firstOrFail();
        $office = User::where('email', 'authority.office@example.test')->firstOrFail();
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $authorityEmployee = Employee::where('employee_number', 'EMP-AUTH-001')->firstOrFail();
        $this->actingAs($authority, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority/approve")->assertOk();
        $this->actingAs($office, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-scheduling/start")->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-participants", ['employee_ids' => [$authorityEmployee->id]])->assertStatus(403);
        $this->actingAs($office, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/high-authority/requester-participants", ['employee_ids' => [$authorityEmployee->id]])->assertStatus(403);
    }

    public function test_forward_stops_when_current_high_authority_holder_is_inactive(): void
    {
        [$request] = $this->prepareOfficeReview();
        $office = User::where('email', 'authority.office@example.test')->firstOrFail();
        Employee::where('employee_number', 'EMP-AUTH-001')->update(['status' => 'INACTIVE']);
        $this->actingAs($office, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/forward")->assertStatus(422)->assertJsonPath('code', 'HIGH_AUTHORITY_HOLDER_NOT_AVAILABLE');
    }

    private function prepareOfficeReview(): array
    {
        [$request, $users] = $this->prepareRequest();
        $this->actingAs($users['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/high-authority/requester-approve")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/review")->assertOk();
        return [$request->fresh(), $users];
    }

    private function prepareDecision(): array
    {
        [$request, $users] = $this->prepareOfficeReview();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/forward")->assertOk();
        return [$request->fresh(), $users];
    }

    private function prepareRequest(): array
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $office = User::where('email', 'authority.office@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $employee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/high-authority', ['requesting_unit_id' => $tech->id, 'subject' => 'درخواست آزمایشی مقام عالی', 'purpose' => 'DECISION', 'meeting_justification' => 'توجیه آزمون workflow.', 'requested_outcome' => 'نتیجهٔ قابل سنجش.', 'priority' => 'NORMAL', 'participant_ids' => [$employee->id]])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-high-authority")->assertOk();
        return [$request, compact('techExec', 'techDirector', 'office')];
    }
}
