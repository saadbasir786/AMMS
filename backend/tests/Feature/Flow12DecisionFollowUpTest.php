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
use Illuminate\Support\Str;
use Tests\TestCase;

class Flow12DecisionFollowUpTest extends TestCase
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
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated()->assertJsonCount(1, 'data');
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated()->assertJsonCount(1, 'data');
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
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$followup->id.'/follow-up/classify', ['classification' => 'NO_ACTION_REQUIRED'])->assertStatus(422)->assertJsonPath('code', 'DECISION_NO_ACTION_REASON_REQUIRED');
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$followup->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $payload = ['title' => 'تهیهٔ برنامهٔ اجرایی', 'description' => 'شرح اقدام', 'responsible_unit_id' => $meeting->organizing_unit_id, 'due_date' => now()->addDays(10)->format('Y-m-d')];
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$followup->id.'/action-items', $payload)->assertCreated()->assertJsonPath('data.action_number', 'DEC-001-A01');
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$followup->id.'/action-items', ['title' => 'ارزیابی تخنیکی', 'responsible_unit_id' => $meeting->organizing_unit_id])->assertCreated()->assertJsonPath('data.action_number', 'DEC-001-A02');
        $this->assertSame(2, MeetingActionItem::count());
        $this->assertSame('برنامه برای اجرا ابلاغ گردد.', $decision->fresh()->decision_text);
    }

    public function test_completion_requires_separate_governance_verification_and_closes_followup(): void
    {
        [$meeting, $owner] = $this->finalizedFixture();
        $employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
        $employee = $employeeUser->employee;
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $followup = MeetingDecisionFollowUp::firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$followup->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $itemResponse = $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$followup->id.'/action-items', ['title' => 'اقدام اجرایی', 'responsible_unit_id' => $meeting->organizing_unit_id, 'assigned_employee_id' => $employee->id, 'due_date' => now()->addDays(5)->format('Y-m-d')])->assertCreated();
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

    public function test_assignment_and_extension_are_controlled_and_source_decision_is_unchanged(): void
    {
        [$meeting, $owner, $decision] = $this->finalizedFixture();
        $employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $followup = MeetingDecisionFollowUp::firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$followup->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $item = $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$followup->id.'/action-items', ['title' => 'اقدام قابل تمدید', 'responsible_unit_id' => $meeting->organizing_unit_id, 'assigned_employee_id' => $employeeUser->employee_id, 'due_date' => now()->addDays(2)->format('Y-m-d')])->assertCreated()->json('data');
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/extensions', ['requested_due_date' => now()->addDays(12)->format('Y-m-d'), 'reason' => 'نیاز به زمان بیشتر'])->assertCreated();
        $extension = \App\Models\MeetingActionItemExtensionRequest::firstOrFail();
        $this->actingAs($employeeUser, 'sanctum')->postJson('/api/action-item-extensions/'.$extension->id.'/approve')->assertStatus(403);
        $this->actingAs($owner, 'sanctum')->postJson('/api/action-item-extensions/'.$extension->id.'/approve')->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->actingAs($owner, 'sanctum')->postJson('/api/action-items/'.$item['id'].'/assignment', ['responsible_unit_id' => $finance->id, 'assigned_employee_id' => null, 'reason' => 'انتقال رسمی مسئولیت'])->assertOk();
        $this->assertSame('برنامه برای اجرا ابلاغ گردد.', $decision->fresh()->decision_text);
        $this->assertSame($decision->target_date?->format('Y-m-d'), $decision->fresh()->target_date?->format('Y-m-d'));
        $this->assertSame(1, MeetingActionItem::findOrFail($item['id'])->assignmentHistory()->count());
    }

    private function finalizedFixture(): array
    {
        $owner = User::where('email', 'tech.coordinator@example.test')->firstOrFail();
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
