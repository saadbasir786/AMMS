<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingExecutionSession;
use App\Models\MeetingMinuteVersion;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\MeetingMinutes;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Flow12PerFlowGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_flow01_local_coordinator_owns_followup_and_authority_actors_are_denied(): void { $this->assertGovernance('MULTI_DIRECTORATE', false, 'tech.exec@example.test'); }
    public function test_flow02_requester_lead_coordinator_owns_followup_and_system_admin_is_denied(): void { $this->assertGovernance('INTER_DIRECTORATE', false, 'tech.exec@example.test'); }
    public function test_flow03_requester_lead_coordinator_owns_followup_and_high_authority_is_denied(): void { $this->assertGovernance('MULTI_DIRECTORATE', false, 'tech.exec@example.test'); }
    public function test_flow04_executive_secretary_owns_followup_and_authority_office_is_denied(): void { $this->assertGovernance('DIRECTORATE_WITH_HIGH_AUTHORITY', true, 'executive.secretary@example.test'); }
    public function test_flow05_executive_secretary_owns_followup_and_high_authority_is_not_owner(): void { $this->assertGovernance('DIRECTORATE_WITH_HIGH_AUTHORITY', true, 'executive.secretary@example.test'); }
    public function test_flow06_executive_secretary_owns_high_authority_initiated_followup_and_requester_is_absent(): void { $this->assertGovernance('HIGH_AUTHORITY_INITIATED', true, 'executive.secretary@example.test', true); }

    private function assertGovernance(string $flowType, bool $authorityFlow, string $ownerEmail, bool $noRequester = false): void
    {
        [$meeting, $decision] = $this->finalizedFixture($flowType, $authorityFlow, $noRequester);
        $owner = User::where('email', $ownerEmail)->firstOrFail();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/follow-up/initialize')->assertCreated();
        $this->actingAs($owner, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertOk();
        foreach (['authority.office@example.test', 'high.authority@example.test', 'system.admin@example.test'] as $email) {
            $actor = User::where('email', $email)->firstOrFail();
            $this->actingAs($actor, 'sanctum')->postJson('/api/decisions/'.$decision->id.'/follow-up/classify', ['classification' => 'ACTION_REQUIRED'])->assertStatus(403);
        }
        if ($noRequester) $this->assertNull($meeting->fresh()->request->requesting_unit_id);
    }

    private function finalizedFixture(string $flowType, bool $authorityFlow, bool $noRequester): array
    {
        $coordinator = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
        $owner = $authorityFlow ? $secretary : $coordinator;
        $unit = OrganizationalUnit::where('code', $authorityFlow ? 'AUTH-OFFICE' : 'TECH')->firstOrFail();
        $employee = Employee::where('email', $authorityFlow ? 'high.authority@example.test' : 'tech.director@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'FLOW12-GOV-'.Str::random(8), 'flow_type' => $flowType, 'requesting_unit_id' => $noRequester ? null : OrganizationalUnit::where('code', 'TECH')->value('id'), 'created_by' => $owner->id, 'subject' => 'FLOW-12 governance '.$flowType, 'purpose' => 'FLOW12_GOVERNANCE', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW12-GOV-M-'.Str::random(8), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->subDay()->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW12-GOV', 'status' => 'COMPLETED']);
        if ($authorityFlow) {
            $authority = OrganizationAuthority::firstOrFail();
            MeetingAuthorityContext::create(['meeting_id' => $meeting->id, 'organization_authority_id' => $authority->id, 'authority_type' => $authority->authority_type, 'authority_position_id' => $authority->authority_position_id, 'authority_employee_id' => Employee::where('employee_number', 'EMP-AUTH-001')->value('id'), 'authority_office_unit_id' => OrganizationalUnit::where('code', 'AUTH-OFFICE')->value('id')]);
        }
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class);
        $scheduleVersion = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $owner->id);
        $schedule->applyToMeeting($meeting, $scheduleVersion);
        $participantVersion = app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);
        $session = MeetingExecutionSession::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'status' => 'ENDED', 'started_at' => now()->subHour(), 'started_by_user_id' => $owner->id, 'ended_at' => now()->subMinutes(30), 'ended_by_user_id' => $owner->id]);
        $minutes = MeetingMinutes::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'status' => MeetingMinutes::FINALIZED, 'created_by_user_id' => $owner->id, 'finalized_at' => now()->subMinutes(10), 'finalized_by_user_id' => $owner->id]);
        $version = MeetingMinuteVersion::create(['uuid' => (string) Str::uuid(), 'meeting_minutes_id' => $minutes->id, 'version_number' => 1, 'execution_session_id' => $session->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'summary' => 'FLOW12 governance', 'prepared_by_user_id' => $owner->id, 'submitted_at' => now()->subMinutes(20)]);
        $decision = MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $version->id, 'sequence_number' => 1, 'decision_number' => 'GOV-DEC-001', 'decision_text' => 'تصمیم حاکمیتی ثبت شد.', 'responsible_unit_id' => $unit->id, 'responsible_employee_id' => $employee->id, 'target_date' => now()->addDays(7)->format('Y-m-d')]);
        $minutes->update(['current_version_id' => $version->id]);
        return [$meeting->fresh(['minutes']), $decision];
    }
}
