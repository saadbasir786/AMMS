<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Flow02Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_two_directorate_request_completes_bilateral_workflow(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $financeDirector = User::where('email', 'finance.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $techEmployee = \App\Models\Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $financeEmployee = \App\Models\Employee::where('employee_number', 'EMP-FIN-002')->firstOrFail();

        $created = $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/inter-directorate', [
            'requesting_unit_id' => $tech->id,
            'target_unit_id' => $finance->id,
            'subject' => 'جلسه هماهنگی بودجه سیستم‌های معلوماتی',
            'purpose' => 'COORDINATION',
            'description' => 'بررسی هماهنگی بودجه.',
            'priority' => 'NORMAL',
            'participant_ids' => [$techEmployee->id, $financeEmployee->id],
        ]);
        $created->assertCreated()->assertJsonPath('data.flow_type', 'INTER_DIRECTORATE')->assertJsonPath('data.status', 'DRAFT');
        $request = MeetingRequest::latest('id')->firstOrFail();

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-inter-directorate")->assertOk()->assertJsonPath('data.status', 'PENDING_REQUESTER_APPROVAL');
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/requester-approve")->assertStatus(403);
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/requester-approve")->assertOk()->assertJsonPath('data.status', 'SUBMITTED_TO_TARGET');

        $this->actingAs($financeExec, 'sanctum')->getJson('/api/incoming-meeting-requests')->assertOk()->assertJsonPath('data.0.id', $request->id);
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-review")->assertOk()->assertJsonPath('data.status', 'UNDER_TARGET_REVIEW');
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-approve")->assertStatus(403);
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-approve")->assertOk()->assertJsonPath('data.status', 'BILATERAL_APPROVED');

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-inter-directorate-scheduling")->assertOk()->assertJsonPath('data.status', 'SCHEDULING');
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/schedule-proposals", [
            'proposed_by_unit_id' => $tech->id, 'meeting_date' => now()->addDays(3)->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '10:00', 'location' => 'اتاق جلسات تکنالوژی',
        ])->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/schedule-proposals", [
            'proposed_by_unit_id' => $finance->id, 'meeting_date' => now()->addDays(4)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'اتاق کنفرانس مرکزی',
        ])->assertOk();
        $this->assertDatabaseCount('meeting_schedule_proposals', 2);
        $counter = \App\Models\MeetingScheduleProposal::where('meeting_request_id', $request->id)->where('proposed_by_unit_id', $finance->id)->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/schedule-proposals/{$counter->id}/accept")->assertOk();

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/participant-nominations", ['nominated_by_unit_id' => $tech->id, 'employee_id' => $techEmployee->id])->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/participant-nominations", ['nominated_by_unit_id' => $finance->id, 'employee_id' => $financeEmployee->id])->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/requester-participants/confirm")->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-participants/confirm")->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/confirm-inter-directorate-schedule")->assertOk()->assertJsonPath('data.status', 'SCHEDULE_CONFIRMED');

        $final = $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize-inter-directorate", [
            'chairperson_employee_id' => $techEmployee->id,
            'agenda' => 'بررسی بودجه و هماهنگی اجراآت.',
        ]);
        $final->assertOk()->assertJsonPath('data.status', 'SCHEDULED');
        $this->assertSame('CONVERTED_TO_MEETING', $request->fresh()->status);
        $this->assertSame(1, Meeting::where('meeting_request_id', $request->id)->count());
        $meeting = Meeting::where('meeting_request_id', $request->id)->firstOrFail();
        $this->assertDatabaseHas('meeting_units', ['meeting_id' => $meeting->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING']);
        $this->assertDatabaseHas('meeting_units', ['meeting_id' => $meeting->id, 'organizational_unit_id' => $finance->id, 'role' => 'PARTICIPATING']);
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meetings/{$meeting->id}/reschedule-cases", [
            'reason' => 'نیاز به بررسی دوبارهٔ زمان جلسه.',
        ])->assertCreated();
        $this->assertDatabaseHas('meeting_request_actions', ['meeting_request_id' => $request->id, 'action' => 'REQUESTER_APPROVED']);
        $this->assertDatabaseHas('meeting_request_actions', ['meeting_request_id' => $request->id, 'action' => 'TARGET_APPROVED']);
        $this->assertDatabaseHas('meeting_request_actions', ['meeting_request_id' => $request->id, 'action' => 'BILATERAL_APPROVED']);
    }

    public function test_target_return_requires_resubmit_and_cannot_be_approved_before_resubmission(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $financeDirector = User::where('email', 'finance.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $employee = \App\Models\Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/inter-directorate', ['requesting_unit_id' => $tech->id, 'target_unit_id' => $finance->id, 'subject' => 'بازگشت مقصد', 'purpose' => 'COORDINATION', 'priority' => 'NORMAL', 'participant_ids' => [$employee->id]])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-inter-directorate")->assertOk();
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/requester-approve")->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-review")->assertOk();
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-return", ['reason' => 'لطفاً سند هزینه اضافه شود.'])->assertOk()->assertJsonPath('data.status', 'RETURNED_BY_TARGET');
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-approve")->assertStatus(422)->assertJsonPath('code', 'REQUEST_NOT_AVAILABLE_TO_TARGET');
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/resubmit-to-target")->assertOk()->assertJsonPath('data.status', 'UNDER_TARGET_REVIEW');
        $this->assertDatabaseHas('meeting_request_actions', ['meeting_request_id' => $request->id, 'action' => 'TARGET_RESUBMITTED']);
    }

    public function test_opposite_side_cannot_nominate_requester_participant_and_third_directorate_is_rejected(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $financeDirector = User::where('email', 'finance.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $techEmployee = \App\Models\Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $financeEmployee = \App\Models\Employee::where('employee_number', 'EMP-FIN-002')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/inter-directorate', ['requesting_unit_id' => $tech->id, 'target_unit_id' => $finance->id, 'subject' => 'مالکیت طرفین', 'purpose' => 'COORDINATION', 'priority' => 'NORMAL', 'participant_ids' => [$techEmployee->id, $financeEmployee->id]])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-inter-directorate")->assertOk();
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/requester-approve")->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-review")->assertOk();
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-approve")->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-inter-directorate-scheduling")->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/participant-nominations", ['nominated_by_unit_id' => $finance->id, 'employee_id' => $financeEmployee->id])->assertStatus(403);

        $hr = \App\Models\OrganizationalUnit::create(['organization_id' => $tech->organization_id, 'parent_id' => null, 'name' => 'ریاست منابع بشری', 'code' => 'HR-NEG', 'unit_type' => 'DIRECTORATE', 'status' => 'ACTIVE']);
        $hrEmployee = \App\Models\Employee::create(['employee_number' => 'EMP-HR-NEG-001', 'first_name' => 'حسن', 'last_name' => 'احمدی', 'father_name' => 'کریم', 'organizational_unit_id' => $hr->id, 'status' => 'ACTIVE']);
        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/inter-directorate', ['requesting_unit_id' => $tech->id, 'target_unit_id' => $finance->id, 'subject' => 'ریاست سوم', 'purpose' => 'COORDINATION', 'priority' => 'NORMAL', 'participant_ids' => [$hrEmployee->id]])->assertStatus(422)->assertJsonPath('code', 'THIRD_DIRECTORATE_REQUIRES_MULTI_DIRECTORATE_FLOW');
    }

    public function test_same_directorate_and_target_scope_are_rejected(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/inter-directorate', [
            'requesting_unit_id' => $tech->id, 'target_unit_id' => $tech->id, 'subject' => 'نامعتبر', 'purpose' => 'COORDINATION', 'priority' => 'NORMAL',
        ])->assertStatus(422)->assertJsonPath('code', 'SAME_REQUESTER_AND_TARGET');
    }
}
