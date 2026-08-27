<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingExecutionSession;
use App\Models\MeetingMinuteVersion;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\MeetingMinutes;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DecisionFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_initialization_requires_completed_finalized_source_and_is_idempotent(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $decoy = MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $decision->meeting_minute_version_id, 'sequence_number' => 2, 'decision_number' => 'DECOY-001', 'decision_text' => 'تصمیم غیرهدف.', 'responsible_unit_id' => $meeting->organizing_unit_id, 'target_date' => now()->addDays(8)->format('Y-m-d')]);
        $decoyFollowup = MeetingDecisionFollowUp::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'meeting_minutes_id' => $meeting->minutes->id, 'meeting_minute_version_id' => $decision->meeting_minute_version_id, 'meeting_decision_id' => $decoy->id, 'decision_number' => $decoy->decision_number, 'classification' => MeetingDecisionFollowUp::UNCLASSIFIED, 'status' => MeetingDecisionFollowUp::OPEN]);
        $decoyFollowup->delete();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated()->assertJsonCount(2, 'data');
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated()->assertJsonCount(2, 'data');
        $targetFollowup = MeetingDecisionFollowUp::where('meeting_decision_id', $decision->id)->firstOrFail();
        $this->assertNotSame((int) $decision->id, (int) $targetFollowup->id);
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk()->assertJsonPath('data.decision_id', $decision->id)->assertJsonPath('data.id', $targetFollowup->id);
        $this->assertSame(1, MeetingDecisionFollowUp::where('meeting_decision_id', $decision->id)->count());
        $this->assertSame('COMPLETED', $meeting->fresh()->status);
        $meeting->update(['status' => 'HELD']);
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertStatus(422)->assertJsonPath('code', 'FOLLOWUP_MEETING_NOT_COMPLETED');
    }

    public function test_no_action_requires_reason_and_action_items_are_deterministically_numbered(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $followup = MeetingDecisionFollowUp::firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'NO_ACTION_REQUIRED'])->assertStatus(422)->assertJsonPath('code', 'DECISION_NO_ACTION_REASON_REQUIRED');
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk()->assertJsonPath('data.status', 'OPEN');
        $payload = ['title' => 'تهیهٔ برنامهٔ اجرایی', 'description' => 'شرح اقدام', 'responsible_unit_id' => $meeting->organizing_unit_id, 'due_date' => now()->addDays(10)->format('Y-m-d')];
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', $payload)->assertCreated()->assertJsonPath('data.action_number', 'DEC-001-A01');
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', ['title' => 'ارزیابی تخنیکی', 'responsible_unit_id' => $meeting->organizing_unit_id])->assertCreated()->assertJsonPath('data.action_number', 'DEC-001-A02');
        $this->assertSame(2, MeetingActionItem::count());
        $this->assertSame('برنامه برای اجرا ابلاغ گردد.', $decision->fresh()->decision_text);
    }

    public function test_no_action_classification_requires_dari_reason_and_resolves_without_action_item(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'NO_ACTION_REQUIRED', 'reason' => 'اقدام جداگانه لازم نیست؛ تصمیم در همان سند اجرایی شد.'])->assertOk()->assertJsonPath('data.classification', 'NO_ACTION_REQUIRED')->assertJsonPath('data.status', 'CLOSED')->assertJsonPath('data.action_item_count', 0);
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', ['title' => 'نباید ساخته شود', 'responsible_unit_id' => $meeting->organizing_unit_id])->assertStatus(403);
    }

    public function test_completion_requires_separate_governance_verification_and_closes_followup(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
        $employee = $employeeUser->employee;
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $followup = MeetingDecisionFollowUp::firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $itemResponse = $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', ['title' => 'اقدام اجرایی', 'responsible_unit_id' => $meeting->organizing_unit_id, 'assigned_employee_id' => $employee->id, 'due_date' => now()->addDays(5)->format('Y-m-d')])->assertCreated();
        $itemId = $itemResponse->json('data.id');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$itemId.'/start')->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$itemId.'/progress', ['progress_percent' => 40, 'note' => 'پیشرفت اول'])->assertOk();
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$itemId.'/block', ['reason' => 'در انتظار اسناد'])->assertOk()->assertJsonPath('data.status', 'BLOCKED');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$itemId.'/unblock', ['note' => 'اسناد دریافت شد'])->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$itemId.'/progress', ['progress_percent' => 100, 'note' => 'کار تکمیل شد'])->assertOk();
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$itemId.'/completion/submit', ['completion_note' => 'برای بررسی ارسال شد'])->assertOk()->assertJsonPath('data.status', 'PENDING_VERIFICATION');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$itemId.'/completion/verify')->assertStatus(403);
        $this->actingAs($owner, 'sanctum')->postJson('/api/action-items/'.$itemId.'/completion/verify')->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->assertSame('CLOSED', $followup->fresh()->status);
        $this->assertSame('COMPLETED', $meeting->fresh()->status);
        $this->assertSame('FINALIZED', $meeting->fresh()->minutes->status);
    }

    public function test_action_item_detail_projects_history_and_capabilities(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $itemId = $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', [
            'title' => 'اقدام برای بررسی جزئیات',
            'responsible_unit_id' => $meeting->organizing_unit_id,
            'assigned_employee_id' => $employeeUser->employee_id,
        ])->assertCreated()->json('data.id');

        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$itemId.'/start')->assertOk();
        $this->actingAs($owner, 'sanctum')->getJson('/api/action-items/'.$itemId)
            ->assertOk()
            ->assertJsonPath('data.updates.0.update_type', 'PROGRESS')
            ->assertJsonPath('data.updates.0.actor.id', $employeeUser->id)
            ->assertJsonPath('data.extensions', [])
            ->assertJsonPath('data.assignment_history', []);

        $this->actingAs($employeeUser, 'sanctum')->getJson('/api/action-items/'.$itemId.'/action-capabilities')
            ->assertOk()
            ->assertJsonPath('data.view', true)
            ->assertJsonPath('data.progress', true)
            ->assertJsonPath('data.verify_completion', false)
            ->assertJsonPath('data.cancel', false);
    }

    public function test_action_item_index_supports_search_and_pagination_metadata(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', ['title' => 'اقدام قابل جست‌وجو', 'responsible_unit_id' => $meeting->organizing_unit_id])->assertCreated();

        $this->actingAs($owner, 'sanctum')->getJson('/api/action-items?search=جست‌وجو&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_assignment_and_extension_are_controlled_and_source_decision_is_unchanged(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $followup = MeetingDecisionFollowUp::firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $item = $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', ['title' => 'اقدام قابل تمدید', 'responsible_unit_id' => $meeting->organizing_unit_id, 'assigned_employee_id' => $employeeUser->employee_id, 'due_date' => now()->addDays(2)->format('Y-m-d')])->assertCreated()->json('data');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/extensions', ['requested_due_date' => now()->addDays(12)->format('Y-m-d'), 'reason' => 'نیاز به زمان بیشتر'])->assertCreated();
        $extension = \App\Models\MeetingActionItemExtensionRequest::firstOrFail();
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-item-extensions/'.$extension->id.'/approve')->assertStatus(403);
        $this->actingAs($owner, 'sanctum')->postJson('/api/action-item-extensions/'.$extension->id.'/approve')->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->actingAs($owner, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/assignment', ['responsible_unit_id' => $finance->id, 'assigned_employee_id' => null, 'reason' => 'انتقال رسمی مسئولیت'])->assertOk();
        $this->assertSame('برنامه برای اجرا ابلاغ گردد.', $decision->fresh()->decision_text);
        $this->assertSame($decision->target_date?->format('Y-m-d'), $decision->fresh()->target_date?->format('Y-m-d'));
        $this->assertSame(1, MeetingActionItem::findOrFail($item['id'])->assignmentHistory()->count());
    }

    public function test_return_resubmit_and_final_verification_are_append_only(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $item = $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', ['title' => 'اقدام برگشتی', 'responsible_unit_id' => $meeting->organizing_unit_id, 'assigned_employee_id' => $employeeUser->employee_id, 'due_date' => now()->addDays(5)->format('Y-m-d')])->assertCreated()->json('data');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/start')->assertOk();
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/progress', ['progress_percent' => 100, 'note' => 'تکمیل اول'])->assertOk();
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/completion/submit', ['completion_note' => 'ارسال اول'])->assertOk()->assertJsonPath('data.status', 'PENDING_VERIFICATION');
        $this->actingAs($owner, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/completion/return', ['reason' => 'لطفاً سند تکمیلی اضافه شود.'])->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/progress', ['progress_percent' => 100, 'note' => 'اصلاح انجام شد'])->assertOk();
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/completion/submit', ['completion_note' => 'ارسال مجدد'])->assertOk()->assertJsonPath('data.status', 'PENDING_VERIFICATION');
        $this->actingAs($owner, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/completion/verify')->assertOk()->assertJsonPath('data.status', 'COMPLETED');
        $this->assertSame(2, \App\Models\MeetingActionItemUpdate::where('action_item_id', $item['id'])->where('update_type', \App\Models\MeetingActionItemUpdate::COMPLETION_SUBMITTED)->count());
        $this->assertSame(1, \App\Models\MeetingActionItemUpdate::where('action_item_id', $item['id'])->where('update_type', \App\Models\MeetingActionItemUpdate::COMPLETION_RETURNED)->count());
    }

    public function test_progress_bounds_and_employee_cannot_cancel_or_mutate_unrelated_item(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
        $unrelated = User::where('email', 'finance.director@example.test')->firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $item = $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', ['title' => 'اقدام محدود', 'responsible_unit_id' => $meeting->organizing_unit_id, 'assigned_employee_id' => $employeeUser->employee_id])->assertCreated()->json('data');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/progress', ['progress_percent' => -1])->assertStatus(422);
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/progress', ['progress_percent' => 101])->assertStatus(422);
        $this->actingAs($unrelated, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/progress', ['progress_percent' => 20])->assertStatus(403);
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/cancel', ['reason' => 'لغو غیرمجاز توسط مسئول اجرایی'])->assertStatus(403);
    }

    public function test_overdue_is_derived_for_open_item_and_terminal_items_are_not_overdue(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $item = $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', ['title' => 'اقدام عقب‌افتاده', 'responsible_unit_id' => $meeting->organizing_unit_id, 'assigned_employee_id' => $employeeUser->employee_id, 'due_date' => now()->subDay()->format('Y-m-d')])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->getJson('/api/action-items/'.$item['id'])->assertOk()->assertJsonPath('data.is_overdue', true);
        $this->actingAs($owner, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/cancel', ['reason' => 'تصمیم جایگزین صادر شد.'])->assertOk()->assertJsonPath('data.status', 'CANCELLED')->assertJsonPath('data.is_overdue', false);
        $this->assertSame(0, \App\Models\MeetingActionItem::where('status', 'OVERDUE')->count());
    }

    public function test_cross_unit_reassignment_requires_owner_and_private_evidence_is_downloadable_only_in_scope(): void
    {
        Storage::fake('local');
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $item = $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', ['title' => 'اقدام با شواهد', 'responsible_unit_id' => $meeting->organizing_unit_id, 'assigned_employee_id' => $employeeUser->employee_id])->assertCreated()->json('data');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/assignment', ['responsible_unit_id' => $finance->id, 'assigned_employee_id' => null, 'reason' => 'تغییر غیرمجاز حوزه'])->assertStatus(403);
        $this->actingAs($owner, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/assignment', ['responsible_unit_id' => $finance->id, 'assigned_employee_id' => null, 'reason' => 'انتقال رسمی مسئولیت'])->assertOk();
        $upload = UploadedFile::fake()->create('completion.pdf', 10, 'application/pdf');
        $response = $this->actingAs($owner, 'sanctum')->post('/api/action-items/'.$item['id'].'/evidence', ['evidence' => $upload]);
        $response->assertCreated()->assertJsonPath('data.public_url', false);
        $evidenceId = $response->json('data.id');
        $this->actingAs($owner, 'sanctum')->get('/api/action-items/'.$item['id'].'/evidence/'.$evidenceId)->assertOk();
    }

    public function test_completion_verification_withdraws_pending_extension_atomically(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
        $originalDueDate = now()->addDays(2)->format('Y-m-d');

        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $item = $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/action-items', [
            'title' => 'اقدام دارای تمدید معلق',
            'responsible_unit_id' => $meeting->organizing_unit_id,
            'assigned_employee_id' => $employeeUser->employee_id,
            'due_date' => $originalDueDate,
        ])->assertCreated()->json('data');

        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/start')->assertOk();
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/progress', ['progress_percent' => 100, 'note' => 'کار تکمیل شد'])->assertOk();
        $extension = $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/extensions', [
            'requested_due_date' => now()->addDays(12)->format('Y-m-d'),
            'reason' => 'درخواست تمدید پیش از تأیید',
        ])->assertCreated()->json('data');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/completion/submit', ['completion_note' => 'برای تأیید ارسال شد'])->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/completion/verify')->assertOk()->assertJsonPath('data.status', 'COMPLETED');

        $resolved = \App\Models\MeetingActionItemExtensionRequest::findOrFail($extension['id']);
        $this->assertSame(\App\Models\MeetingActionItemExtensionRequest::WITHDRAWN, $resolved->status);
        $this->assertNotNull($resolved->reviewed_at);
        $this->assertSame('ACTION_ITEM_COMPLETED_BEFORE_EXTENSION_REVIEW', $resolved->review_reason);
        $this->assertSame(0, \App\Models\MeetingActionItemExtensionRequest::where('action_item_id', $item['id'])->where('status', 'PENDING')->count());
        $this->assertSame($originalDueDate, MeetingActionItem::findOrFail($item['id'])->due_date?->format('Y-m-d'));
        $this->assertSame($decision->target_date?->format('Y-m-d'), $decision->fresh()->target_date?->format('Y-m-d'));
        $this->assertDatabaseHas('meeting_actions', ['action' => 'ACTION_ITEM_EXTENSION_WITHDRAWN']);
    }

    private function finalizedFixture(): array
    {
        $owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $unit = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $employee = Employee::where('email', 'tech.director@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'FLOW12-'.uniqid(), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $owner->id, 'subject' => 'جلسهٔ پیگیری تصمیم FLOW-12', 'purpose' => 'MINUTES', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW12-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->subDay()->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار پیگیری', 'status' => 'COMPLETED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class);
        $scheduleVersion = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $owner->id);
        $schedule->applyToMeeting($meeting, $scheduleVersion);
        $participantVersion = app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);
        $session = MeetingExecutionSession::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'status' => 'ENDED', 'started_at' => now()->subHour(), 'started_by_user_id' => $owner->id, 'ended_at' => now()->subMinutes(30), 'ended_by_user_id' => $owner->id]);
        $minutes = MeetingMinutes::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'status' => MeetingMinutes::FINALIZED, 'created_by_user_id' => $owner->id, 'finalized_at' => now()->subMinutes(10), 'finalized_by_user_id' => $owner->id]);
        $version = MeetingMinuteVersion::create(['uuid' => (string) Str::uuid(), 'meeting_minutes_id' => $minutes->id, 'version_number' => 1, 'execution_session_id' => $session->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'summary' => 'خلاصهٔ نهایی', 'prepared_by_user_id' => $owner->id, 'submitted_at' => now()->subMinutes(20)]);
        $decision = MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $version->id, 'sequence_number' => 1, 'decision_number' => 'DEC-001', 'decision_text' => 'برنامه برای اجرا ابلاغ گردد.', 'responsible_unit_id' => $unit->id, 'responsible_employee_id' => $employee->id, 'target_date' => now()->addDays(7)->format('Y-m-d'), 'notes' => 'یادداشت تصمیم']);
        $minutes->update(['current_version_id' => $version->id]);
        return [$meeting->fresh(['minutes']), $owner, $decision];
    }
}
