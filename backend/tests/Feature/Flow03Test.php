<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\Organization;
use App\Models\Position;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Flow03Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_three_directorate_request_completes_multilateral_workflow(): void
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
        $techEmployee = \App\Models\Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $financeEmployee = \App\Models\Employee::where('employee_number', 'EMP-FIN-002')->firstOrFail();
        $hrEmployee = \App\Models\Employee::where('employee_number', 'EMP-HR-002')->firstOrFail();
        $chairperson = \App\Models\Employee::where('employee_number', 'EMP-TECH-001')->firstOrFail();

        $created = $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $tech->id,
            'target_unit_ids' => [$finance->id, $hr->id],
            'subject' => 'جلسه هماهنگی پلان تطبیق سیستم اداری',
            'purpose' => 'COORDINATION',
            'description' => 'هماهنگی پلان تطبیق سیستم اداری بین سه ریاست.',
            'priority' => 'NORMAL',
            'participant_ids' => [$techEmployee->id, $financeEmployee->id, $hrEmployee->id],
        ]);
        $created->assertCreated()->assertJsonPath('data.flow_type', 'MULTI_DIRECTORATE')->assertJsonPath('data.status', 'DRAFT');
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->assertDatabaseCount('meeting_request_units', 3);
        $this->assertDatabaseCount('meeting_request_unit_reviews', 2);

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-multi")->assertOk()->assertJsonPath('data.status', 'PENDING_REQUESTER_APPROVAL');
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/requester-approve")->assertOk()->assertJsonPath('data.status', 'SUBMITTED_TO_TARGETS');

        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$finance->id}/review")->assertOk()->assertJsonPath('data.status', 'UNDER_MULTI_TARGET_REVIEW');
        $this->actingAs($hrExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$hr->id}/review")->assertOk();
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$finance->id}/approve")->assertOk();
        $this->actingAs($hrDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$hr->id}/approve")->assertOk()->assertJsonPath('data.status', 'MULTILATERAL_APPROVED');

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-multi-scheduling")->assertOk()->assertJsonPath('data.status', 'SCHEDULING');
        $proposal = $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/schedule-proposals", [
            'proposed_by_unit_id' => $tech->id,
            'meeting_date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'location' => 'تالار کنفرانس مرکزی',
        ])->assertOk();
        $proposalId = $proposal->json('data.schedule_proposals.0.id') ?: MeetingScheduleProposal::where('meeting_request_id', $request->id)->latest('id')->value('id');
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/schedule-proposals/{$proposalId}/accept", ['unit_id' => $finance->id])->assertOk();
        $this->actingAs($hrExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/schedule-proposals/{$proposalId}/accept", ['unit_id' => $hr->id])->assertOk();

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/units/{$tech->id}/participants", ['organizational_unit_id' => $tech->id, 'employee_id' => $techEmployee->id])->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/units/{$finance->id}/participants", ['organizational_unit_id' => $finance->id, 'employee_id' => $financeEmployee->id])->assertOk();
        $this->actingAs($hrExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/units/{$hr->id}/participants", ['organizational_unit_id' => $hr->id, 'employee_id' => $hrEmployee->id])->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/units/{$tech->id}/participants/confirm")->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/units/{$finance->id}/participants/confirm")->assertOk();
        $this->actingAs($hrExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/units/{$hr->id}/participants/confirm")->assertOk();

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/confirm-multi-schedule")->assertOk()->assertJsonPath('data.status', 'SCHEDULE_CONFIRMED');
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize-multi", [
            'chairperson_employee_id' => $chairperson->id,
            'agenda' => 'بررسی پلان، هماهنگی مسئولیت‌ها و تعیین اقدامات بعدی.',
        ])->assertOk();

        $this->assertSame('CONVERTED_TO_MEETING', $request->fresh()->status);
        $meeting = Meeting::where('meeting_request_id', $request->id)->firstOrFail();
        $this->assertSame(3, $meeting->units()->count());
        $this->assertSame(3, $meeting->participants()->count());
        $this->assertDatabaseCount('meeting_schedule_proposal_acceptances', 3);
        $this->assertDatabaseCount('meeting_request_unit_participant_confirmations', 3);
        $this->assertDatabaseHas('meeting_request_actions', ['meeting_request_id' => $request->id, 'action' => 'MULTILATERAL_APPROVED']);
    }

    public function test_material_change_resets_target_reviews_and_wrong_unit_is_forbidden(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $financeDirector = User::where('email', 'finance.director@example.test')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $techEmployee = \App\Models\Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $financeEmployee = \App\Models\Employee::where('employee_number', 'EMP-FIN-002')->firstOrFail();
        $hrEmployee = \App\Models\Employee::where('employee_number', 'EMP-HR-002')->firstOrFail();

        $response = $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $tech->id,
            'target_unit_ids' => [$finance->id, $hr->id],
            'subject' => 'تغییر material',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'participant_ids' => [$techEmployee->id, $financeEmployee->id, $hrEmployee->id],
        ])->assertCreated();
        $request = MeetingRequest::findOrFail($response->json('data.id'));
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-multi")->assertOk();
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/requester-approve")->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$finance->id}/review")->assertOk();
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$finance->id}/return", ['reason' => 'لطفاً زمان پیشنهادی را واضح‌تر درج کنید.'])->assertOk()->assertJsonPath('data.status', 'TARGET_ACTION_REQUIRED');

        $this->actingAs($financeExec, 'sanctum')->putJson("/api/meeting-requests/{$request->id}/multi", [
            'subject' => 'عنوان material اصلاح‌شده',
            'target_unit_ids' => [$finance->id, $hr->id],
        ])->assertStatus(403);

        $this->actingAs($techExec, 'sanctum')->putJson("/api/meeting-requests/{$request->id}/multi", [
            'subject' => 'عنوان material اصلاح‌شده',
            'target_unit_ids' => [$finance->id, $hr->id],
            'description' => 'شرح اصلاح‌شده.',
        ])->assertOk()->assertJsonPath('data.status', 'PENDING_REQUESTER_APPROVAL');
        $this->assertDatabaseHas('meeting_request_actions', ['meeting_request_id' => $request->id, 'action' => 'MATERIAL_CHANGE_REQUIRES_REAPPROVAL']);
        $this->assertDatabaseHas('meeting_request_unit_reviews', ['meeting_request_id' => $request->id, 'organizational_unit_id' => $finance->id, 'review_status' => 'PENDING_REVIEW']);
    }

    public function test_finance_director_cannot_approve_hr_target(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertOk();
        $this->actingAs($ctx['hrExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['hr']->id}/review")->assertOk();

        $this->actingAs($ctx['financeDirector'], 'sanctum')
            ->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['hr']->id}/approve")
            ->assertForbidden();
    }

    public function test_tech_cannot_confirm_finance_participants(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), true);
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/units/{$ctx['finance']->id}/participants", [
            'organizational_unit_id' => $ctx['finance']->id,
            'employee_id' => $ctx['financeEmployee']->id,
        ])->assertOk();

        $this->actingAs($ctx['techExec'], 'sanctum')
            ->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/units/{$ctx['finance']->id}/participants/confirm")
            ->assertForbidden();
    }

    public function test_participant_outside_multi_directorate_scope_is_rejected(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), true);
        $organization = Organization::firstOrFail();
        $procurement = OrganizationalUnit::create([
            'organization_id' => $organization->id,
            'name' => 'ریاست تدارکات',
            'code' => 'PROC-NEG',
            'unit_type' => 'DIRECTORATE',
            'status' => 'ACTIVE',
        ]);
        $position = Position::create([
            'organizational_unit_id' => $procurement->id,
            'title' => 'کارمند تدارکات',
            'position_type' => 'EMPLOYEE',
            'status' => 'ACTIVE',
        ]);
        $employee = \App\Models\Employee::create([
            'employee_number' => 'EMP-PROC-NEG-001',
            'first_name' => 'کارمند',
            'last_name' => 'تدارکات',
            'father_name' => 'آزمایش',
            'organizational_unit_id' => $procurement->id,
            'position_id' => $position->id,
            'email' => 'procurement.negative@example.test',
            'phone' => '0700000998',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($ctx['financeExec'], 'sanctum')
            ->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/units/{$ctx['finance']->id}/participants", [
                'organizational_unit_id' => $ctx['finance']->id,
                'employee_id' => $employee->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PARTICIPANT_OUTSIDE_MULTI_DIRECTORATE_SCOPE');
    }

    public function test_schedule_cannot_be_finalized_before_all_units_accept(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), false);
        $proposalId = MeetingScheduleProposal::where('meeting_request_id', $ctx['request']->id)->latest('id')->value('id');
        $this->actingAs($ctx['financeExec'], 'sanctum')
            ->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals/{$proposalId}/accept", ['unit_id' => $ctx['finance']->id])
            ->assertOk();

        $this->actingAs($ctx['techExec'], 'sanctum')
            ->postJson("/api/meeting-requests/{$ctx['request']->id}/confirm-multi-schedule")
            ->assertStatus(422)
            ->assertJsonPath('code', 'SCHEDULE_NOT_ACCEPTED_BY_ALL_UNITS');

        $this->actingAs($ctx['techExec'], 'sanctum')
            ->postJson("/api/meeting-requests/{$ctx['request']->id}/finalize-multi", [
                'chairperson_employee_id' => $ctx['chairperson']->id,
                'agenda' => 'آزمایش نهایی‌سازی زودهنگام.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'SCHEDULE_NOT_CONFIRMED');
    }

    public function test_finalization_rejects_client_schedule_override_fields(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), true);
        $this->nominateAndConfirmAll($ctx);
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/confirm-multi-schedule")->assertOk();
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/finalize-multi", [
            'chairperson_employee_id' => $ctx['chairperson']->id,
            'location' => 'تالار مرکزی',
            'end_time' => '10:00',
            'agenda' => 'نباید زمان‌بندی server-owned را override کند.',
        ])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_duplicate_finalization_creates_only_one_meeting(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), true);
        $this->nominateAndConfirmAll($ctx);
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/confirm-multi-schedule")->assertOk();
        $payload = [
            'chairperson_employee_id' => $ctx['chairperson']->id,
            'agenda' => 'آزمایش جلوگیری از ایجاد جلسهٔ تکراری.',
        ];
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/finalize-multi", $payload)->assertOk();
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/finalize-multi", $payload)->assertForbidden();

        $this->assertSame(1, Meeting::where('meeting_request_id', $ctx['request']->id)->count());
    }

    public function test_multi_request_requires_at_least_two_targets(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $tech->id, 'target_unit_ids' => [$finance->id], 'subject' => 'کمتر از سه ریاست',
            'purpose' => 'COORDINATION', 'priority' => 'NORMAL',
        ])->assertStatus(422);
    }

    public function test_requester_cannot_also_be_target(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $tech->id, 'target_unit_ids' => [$tech->id, $finance->id, $hr->id], 'subject' => 'درخواست‌کننده مقصد نیست',
            'purpose' => 'COORDINATION', 'priority' => 'NORMAL',
        ])->assertStatus(422)->assertJsonPath('code', 'REQUESTER_CANNOT_BE_TARGET');
    }

    public function test_duplicate_target_cannot_be_added(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $tech->id, 'target_unit_ids' => [$finance->id, $finance->id, $hr->id], 'subject' => 'مقصد تکراری',
            'purpose' => 'COORDINATION', 'priority' => 'NORMAL',
        ])->assertStatus(422);
    }

    public function test_cross_organization_target_is_rejected(): void
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
        $otherOrg = Organization::create(['name' => 'سازمان آزمایشی', 'short_name' => 'آزمایشی', 'type' => 'GOVERNMENT', 'status' => 'ACTIVE']);
        $other = OrganizationalUnit::create(['organization_id' => $otherOrg->id, 'name' => 'ریاست بیرونی', 'code' => 'EXT-NEG', 'unit_type' => 'DIRECTORATE', 'status' => 'ACTIVE']);
        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $tech->id, 'target_unit_ids' => [$finance->id, $hr->id, $other->id], 'subject' => 'سازمان متفاوت',
            'purpose' => 'COORDINATION', 'priority' => 'NORMAL',
        ])->assertStatus(422)->assertJsonPath('code', 'CROSS_ORGANIZATION_MEETING_NOT_ALLOWED');
    }

    public function test_unauthorized_creator_cannot_create_for_other_scope(): void
    {
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
        $this->actingAs($financeExec, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $tech->id, 'target_unit_ids' => [$finance->id, $hr->id], 'subject' => 'مالکیت نادرست',
            'purpose' => 'COORDINATION', 'priority' => 'NORMAL',
        ])->assertForbidden();
    }

    public function test_targets_do_not_receive_request_before_requester_approval(): void
    {
        $ctx = $this->createDraftContext();
        $this->actingAs($ctx['financeExec'], 'sanctum')->getJson('/api/incoming-multi-directorate-meeting-requests')->assertJsonMissing(['id' => $ctx['request']->id]);
    }

    public function test_wrong_requester_director_cannot_approve(): void
    {
        $ctx = $this->createDraftContext();
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/submit-multi")->assertOk();
        $this->actingAs($ctx['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/requester-approve")->assertForbidden();
    }

    public function test_requester_return_and_resubmit_work(): void
    {
        $ctx = $this->createDraftContext();
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/submit-multi")->assertOk();
        $this->actingAs($ctx['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/requester-return", ['reason' => 'لطفاً شرح تکمیل شود.'])->assertOk()->assertJsonPath('data.status', 'RETURNED_TO_REQUESTER');
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/resubmit-multi")->assertOk()->assertJsonPath('data.status', 'PENDING_REQUESTER_APPROVAL');
    }

    public function test_requester_rejection_is_terminal(): void
    {
        $ctx = $this->createDraftContext();
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/submit-multi")->assertOk();
        $this->actingAs($ctx['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/requester-reject", ['reason' => 'رد داخلی برای آزمایش.'])->assertOk()->assertJsonPath('data.status', 'REJECTED_INTERNAL');
        $this->actingAs($ctx['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/requester-approve")->assertStatus(422)->assertJsonPath('code', 'REQUEST_NOT_PENDING_REQUESTER_APPROVAL');
    }

    public function test_all_target_units_appear_after_requester_approval(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $response = $this->actingAs($ctx['financeExec'], 'sanctum')->getJson('/api/incoming-multi-directorate-meeting-requests');
        $response->assertOk()->assertJsonFragment(['id' => $ctx['request']->id]);
    }

    public function test_finance_can_start_only_finance_review(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertOk();
        $this->assertDatabaseHas('meeting_request_unit_reviews', ['meeting_request_id' => $ctx['request']->id, 'organizational_unit_id' => $ctx['finance']->id, 'review_status' => 'UNDER_REVIEW']);
        $this->assertDatabaseHas('meeting_request_unit_reviews', ['meeting_request_id' => $ctx['request']->id, 'organizational_unit_id' => $ctx['hr']->id, 'review_status' => 'PENDING_REVIEW']);
    }

    public function test_hr_can_start_only_hr_review(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['hrExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['hr']->id}/review")->assertOk();
        $this->assertDatabaseHas('meeting_request_unit_reviews', ['meeting_request_id' => $ctx['request']->id, 'organizational_unit_id' => $ctx['hr']->id, 'review_status' => 'UNDER_REVIEW']);
        $this->assertDatabaseHas('meeting_request_unit_reviews', ['meeting_request_id' => $ctx['request']->id, 'organizational_unit_id' => $ctx['finance']->id, 'review_status' => 'PENDING_REVIEW']);
    }

    public function test_each_target_approves_independently(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertOk();
        $this->actingAs($ctx['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/approve")->assertOk();
        $this->assertDatabaseHas('meeting_request_unit_reviews', ['meeting_request_id' => $ctx['request']->id, 'organizational_unit_id' => $ctx['finance']->id, 'review_status' => 'APPROVED']);
    }

    public function test_partial_target_approval_does_not_produce_multilateral_approval(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertOk();
        $this->actingAs($ctx['hrExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['hr']->id}/review")->assertOk();
        $this->actingAs($ctx['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/approve")->assertOk();
        $this->assertNotSame('MULTILATERAL_APPROVED', $ctx['request']->fresh()->status);
    }

    public function test_non_material_correction_preserves_unrelated_approval(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertOk();
        $this->actingAs($ctx['hrExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['hr']->id}/review")->assertOk();
        $this->actingAs($ctx['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/approve")->assertOk();
        $this->actingAs($ctx['hrDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['hr']->id}/return", ['reason' => 'وضاحت لازم است.'])->assertOk();
        $this->actingAs($ctx['techExec'], 'sanctum')->putJson("/api/meeting-requests/{$ctx['request']->id}/multi", ['description' => 'شرح non-material اصلاح شد.'])->assertOk();
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/resubmit-multi")->assertOk();
        $this->assertDatabaseHas('meeting_request_unit_reviews', ['meeting_request_id' => $ctx['request']->id, 'organizational_unit_id' => $ctx['finance']->id, 'review_status' => 'APPROVED']);
        $this->assertDatabaseHas('meeting_request_unit_reviews', ['meeting_request_id' => $ctx['request']->id, 'organizational_unit_id' => $ctx['hr']->id, 'review_status' => 'PENDING_REVIEW']);
    }

    public function test_material_correction_invalidates_target_approvals(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertOk();
        $this->actingAs($ctx['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/approve")->assertOk();
        $this->actingAs($ctx['hrExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['hr']->id}/review")->assertOk();
        $this->actingAs($ctx['hrDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['hr']->id}/return", ['reason' => 'اصلاح مادی آزمایشی.'])->assertOk();
        $this->actingAs($ctx['techExec'], 'sanctum')->putJson("/api/meeting-requests/{$ctx['request']->id}/multi", ['subject' => 'موضوع material جدید'])->assertOk()->assertJsonPath('data.status', 'PENDING_REQUESTER_APPROVAL');
        $this->assertDatabaseHas('meeting_request_unit_reviews', ['meeting_request_id' => $ctx['request']->id, 'organizational_unit_id' => $ctx['finance']->id, 'review_status' => 'PENDING_REVIEW']);
    }

    public function test_target_rejection_is_terminal(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertOk();
        $this->actingAs($ctx['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/reject", ['reason' => 'رد نهایی آزمایشی.'])->assertOk()->assertJsonPath('data.status', 'REJECTED_BY_TARGET');
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/start-multi-scheduling")->assertStatus(422);
    }

    public function test_scheduling_cannot_begin_before_all_target_approvals(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertOk();
        $this->actingAs($ctx['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/approve")->assertOk();
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/start-multi-scheduling")->assertStatus(422)->assertJsonPath('code', 'MULTILATERAL_APPROVAL_REQUIRED');
    }

    public function test_any_authorized_participating_coordinator_can_propose_schedule(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), false);
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals", [
            'proposed_by_unit_id' => $ctx['finance']->id, 'meeting_date' => now()->addDays(8)->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '10:00', 'location' => 'تالار دوم',
        ])->assertOk();
    }

    public function test_schedule_proposal_history_is_append_only(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), false);
        $this->assertSame(1, MeetingScheduleProposal::where('meeting_request_id', $ctx['request']->id)->count());
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals", [
            'proposed_by_unit_id' => $ctx['finance']->id, 'meeting_date' => now()->addDays(9)->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '10:00', 'location' => 'تالار دوم',
        ])->assertOk();
        $this->assertSame(2, MeetingScheduleProposal::where('meeting_request_id', $ctx['request']->id)->count());
    }

    public function test_counter_proposal_supersedes_previous_active_proposal(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), false);
        $old = MeetingScheduleProposal::where('meeting_request_id', $ctx['request']->id)->latest('id')->firstOrFail();
        $this->actingAs($ctx['hrExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals", [
            'proposed_by_unit_id' => $ctx['hr']->id, 'meeting_date' => now()->addDays(10)->format('Y-m-d'), 'start_time' => '11:00', 'end_time' => '12:00', 'location' => 'تالار سوم',
        ])->assertOk();
        $this->assertSame('SUPERSEDED', $old->fresh()->status);
        $this->assertSame('COUNTER_PROPOSED', MeetingScheduleProposal::where('meeting_request_id', $ctx['request']->id)->latest('id')->value('status'));
    }

    public function test_old_proposal_acceptances_do_not_transfer(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), false);
        $old = MeetingScheduleProposal::where('meeting_request_id', $ctx['request']->id)->latest('id')->firstOrFail();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals/{$old->id}/accept", ['unit_id' => $ctx['finance']->id])->assertOk();
        $this->actingAs($ctx['hrExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals", [
            'proposed_by_unit_id' => $ctx['hr']->id, 'meeting_date' => now()->addDays(11)->format('Y-m-d'), 'start_time' => '11:00', 'end_time' => '12:00', 'location' => 'تالار سوم',
        ])->assertOk();
        $new = MeetingScheduleProposal::where('meeting_request_id', $ctx['request']->id)->latest('id')->firstOrFail();
        $this->assertSame(1, $new->acceptances()->count());
        $this->assertSame(2, $old->acceptances()->count());
        $this->assertNotSame($old->id, $new->id);
    }

    public function test_each_unit_can_accept_active_schedule_independently(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), false);
        $proposal = MeetingScheduleProposal::where('meeting_request_id', $ctx['request']->id)->latest('id')->firstOrFail();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals/{$proposal->id}/accept", ['unit_id' => $ctx['finance']->id])->assertOk();
        $this->actingAs($ctx['hrExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals/{$proposal->id}/accept", ['unit_id' => $ctx['hr']->id])->assertOk();
        $this->assertSame(3, $proposal->fresh()->acceptances()->count());
    }

    public function test_each_directorate_can_nominate_only_its_own_employee(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), true);
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/units/{$ctx['finance']->id}/participants", ['organizational_unit_id' => $ctx['finance']->id, 'employee_id' => $ctx['techEmployee']->id])->assertStatus(422)->assertJsonPath('code', 'PARTICIPANT_OUTSIDE_MULTI_DIRECTORATE_SCOPE');
    }

    public function test_meeting_cannot_finalize_until_all_participant_lists_confirmed(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), true);
        foreach ([[$ctx['techExec'], $ctx['tech'], $ctx['techEmployee']], [$ctx['financeExec'], $ctx['finance'], $ctx['financeEmployee']]] as [$actor, $unit, $employee]) {
            $this->actingAs($actor, 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/units/{$unit->id}/participants", ['organizational_unit_id' => $unit->id, 'employee_id' => $employee->id])->assertOk();
            $this->actingAs($actor, 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/units/{$unit->id}/participants/confirm")->assertOk();
        }
        $this->actingAs($ctx['techExec'], 'sanctum')
            ->postJson("/api/meeting-requests/{$ctx['request']->id}/confirm-multi-schedule")
            ->assertStatus(422)
            ->assertJsonPath('code', 'UNIT_PARTICIPANTS_NOT_CONFIRMED');
    }

    public function test_timeline_stores_unit_specific_target_events(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertOk();
        $action = \App\Models\MeetingRequestAction::where('meeting_request_id', $ctx['request']->id)->where('action', 'TARGET_REVIEW_STARTED')->latest('id')->firstOrFail();
        $this->assertSame($ctx['finance']->id, (int) ($action->metadata['unit_id'] ?? 0));
    }

    public function test_inactive_user_cannot_perform_privileged_operation(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $ctx['financeExec']->update(['status' => 'INACTIVE']);
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertForbidden();
    }

    public function test_audit_history_is_immutable(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $audit = \App\Models\MeetingRequestAction::where('meeting_request_id', $ctx['request']->id)->firstOrFail();
        $this->expectException(\LogicException::class);
        $audit->update(['comment' => 'تغییر غیرمجاز']);
    }

    public function test_target_cannot_be_approved_twice(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertOk();
        $this->actingAs($ctx['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/approve")->assertOk();
        $this->actingAs($ctx['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/approve")->assertStatus(422);
    }

    public function test_target_cannot_approve_before_review_started(): void
    {
        $ctx = $this->createApprovedRequesterSide();
        $this->actingAs($ctx['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/approve")->assertStatus(422);
    }

    public function test_duplicate_schedule_acceptance_is_rejected(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), false);
        $proposal = MeetingScheduleProposal::where('meeting_request_id', $ctx['request']->id)->latest('id')->firstOrFail();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals/{$proposal->id}/accept", ['unit_id' => $ctx['finance']->id])->assertOk();
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals/{$proposal->id}/accept", ['unit_id' => $ctx['finance']->id])->assertStatus(422);
    }

    public function test_duplicate_participant_confirmation_is_rejected(): void
    {
        $ctx = $this->enterScheduling($this->createApprovedRequesterSide(), true);
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/units/{$ctx['tech']->id}/participants", ['organizational_unit_id' => $ctx['tech']->id, 'employee_id' => $ctx['techEmployee']->id])->assertOk();
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/units/{$ctx['tech']->id}/participants/confirm")->assertOk();
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/units/{$ctx['tech']->id}/participants/confirm")->assertStatus(422);
    }

    public function test_requester_approval_cannot_be_repeated(): void
    {
        $ctx = $this->createDraftContext();
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/submit-multi")->assertOk();
        $this->actingAs($ctx['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/requester-approve")->assertOk();
        $this->actingAs($ctx['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/requester-approve")->assertStatus(422);
    }

    private function createDraftContext(): array
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
        $techEmployee = \App\Models\Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $financeEmployee = \App\Models\Employee::where('employee_number', 'EMP-FIN-002')->firstOrFail();
        $hrEmployee = \App\Models\Employee::where('employee_number', 'EMP-HR-002')->firstOrFail();
        $chairperson = \App\Models\Employee::where('employee_number', 'EMP-TECH-001')->firstOrFail();
        $response = $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $tech->id, 'target_unit_ids' => [$finance->id, $hr->id], 'subject' => 'تست FLOW-03', 'purpose' => 'COORDINATION', 'priority' => 'NORMAL',
            'participant_ids' => [$techEmployee->id, $financeEmployee->id, $hrEmployee->id],
        ])->assertCreated();
        return ['request' => MeetingRequest::findOrFail($response->json('data.id')), 'techExec' => $techExec, 'techDirector' => $techDirector, 'financeExec' => $financeExec, 'financeDirector' => $financeDirector, 'hrExec' => $hrExec, 'hrDirector' => $hrDirector, 'tech' => $tech, 'finance' => $finance, 'hr' => $hr, 'techEmployee' => $techEmployee, 'financeEmployee' => $financeEmployee, 'hrEmployee' => $hrEmployee, 'chairperson' => $chairperson];
    }

    private function createApprovedRequesterSide(): array
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
        $techEmployee = \App\Models\Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $financeEmployee = \App\Models\Employee::where('employee_number', 'EMP-FIN-002')->firstOrFail();
        $hrEmployee = \App\Models\Employee::where('employee_number', 'EMP-HR-002')->firstOrFail();
        $chairperson = \App\Models\Employee::where('employee_number', 'EMP-TECH-001')->firstOrFail();

        $response = $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $tech->id,
            'target_unit_ids' => [$finance->id, $hr->id],
            'subject' => 'تست منفی FLOW-03',
            'purpose' => 'COORDINATION',
            'description' => 'درخواست آزمایشی برای تست محدودیت‌ها.',
            'priority' => 'NORMAL',
            'participant_ids' => [$techEmployee->id, $financeEmployee->id, $hrEmployee->id],
        ])->assertCreated();
        $request = MeetingRequest::findOrFail($response->json('data.id'));
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-multi")->assertOk();
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/requester-approve")->assertOk();

        return compact('request', 'techExec', 'techDirector', 'financeExec', 'financeDirector', 'hrExec', 'hrDirector', 'tech', 'finance', 'hr', 'techEmployee', 'financeEmployee', 'hrEmployee', 'chairperson');
    }

    private function approveAllTargets(array $ctx): void
    {
        $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/review")->assertOk();
        $this->actingAs($ctx['hrExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['hr']->id}/review")->assertOk();
        $this->actingAs($ctx['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['finance']->id}/approve")->assertOk();
        $this->actingAs($ctx['hrDirector'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/target-units/{$ctx['hr']->id}/approve")->assertOk();
    }

    private function enterScheduling(array $ctx, bool $acceptAll): array
    {
        $this->approveAllTargets($ctx);
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/start-multi-scheduling")->assertOk();
        $this->actingAs($ctx['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals", [
            'proposed_by_unit_id' => $ctx['tech']->id,
            'meeting_date' => now()->addDays(7)->format('Y-m-d'),
            'start_time' => '10:00',
            'end_time' => '11:00',
            'location' => 'تالار کنفرانس مرکزی',
        ])->assertOk();
        $proposalId = MeetingScheduleProposal::where('meeting_request_id', $ctx['request']->id)->latest('id')->value('id');
        if ($acceptAll) {
            $this->actingAs($ctx['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals/{$proposalId}/accept", ['unit_id' => $ctx['finance']->id])->assertOk();
            $this->actingAs($ctx['hrExec'], 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/schedule-proposals/{$proposalId}/accept", ['unit_id' => $ctx['hr']->id])->assertOk();
        }
        return $ctx;
    }

    private function nominateAndConfirmAll(array $ctx): void
    {
        foreach ([
            [$ctx['techExec'], $ctx['tech'], $ctx['techEmployee']],
            [$ctx['financeExec'], $ctx['finance'], $ctx['financeEmployee']],
            [$ctx['hrExec'], $ctx['hr'], $ctx['hrEmployee']],
        ] as [$actor, $unit, $employee]) {
            $this->actingAs($actor, 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/units/{$unit->id}/participants", [
                'organizational_unit_id' => $unit->id,
                'employee_id' => $employee->id,
            ])->assertOk();
            $this->actingAs($actor, 'sanctum')->postJson("/api/meeting-requests/{$ctx['request']->id}/multi/units/{$unit->id}/participants/confirm")->assertOk();
        }
    }
}
