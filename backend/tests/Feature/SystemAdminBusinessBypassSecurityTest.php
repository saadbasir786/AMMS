<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
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
use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAdminBusinessBypassSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_system_admin_is_denied_lifecycle_valid_business_governance_operations(): void
    {
        [$users, $units, $employees] = $this->usersAndUnits();

        $flow01 = $this->prepareFlow01($users, $units, $employees);
        $flow01Before = $flow01->fresh()->status;
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/meeting-requests/{$flow01->id}/start-scheduling")
            ->assertForbidden();
        $this->assertSame($flow01Before, $flow01->fresh()->status);

        $flow02 = $this->prepareFlow02($users, $units, $employees);
        $flow02Before = $flow02->fresh()->status;
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/meeting-requests/{$flow02->id}/start-inter-directorate-scheduling")
            ->assertForbidden();
        $this->assertSame($flow02Before, $flow02->fresh()->status);

        $flow03 = $this->prepareFlow03($users, $units, $employees);
        $flow03Before = $flow03->fresh()->status;
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/meeting-requests/{$flow03->id}/start-multi-scheduling")
            ->assertForbidden();
        $this->assertSame($flow03Before, $flow03->fresh()->status);

        $flow04 = $this->prepareFlow04Decision($users, $units, $employees);
        $flow04Before = $flow04->fresh()->status;
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/meeting-requests/{$flow04->id}/authority/approve")
            ->assertForbidden();
        $this->assertSame($flow04Before, $flow04->fresh()->status);

        $this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$flow04->id}/authority/approve")
            ->assertOk();
        $flow04Approved = $flow04->fresh()->status;
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/meeting-requests/{$flow04->id}/authority-scheduling/start")
            ->assertForbidden();
        $this->assertSame($flow04Approved, $flow04->fresh()->status);
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/meeting-requests/{$flow04->id}/authority-participants", ['employee_ids' => [$employees['authority']]])
            ->assertForbidden();

        $flow06 = $this->prepareFlow06Scheduling($users, $units, $employees);
        $flow06Issuance = $flow06->fresh()->status;
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/meeting-requests/{$flow06->id}/authority-initiated/issue")
            ->assertForbidden();
        $this->assertSame($flow06Issuance, $flow06->fresh()->status);
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/meeting-requests/{$flow06->id}/authority-initiated/scheduling/start")
            ->assertForbidden();
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/meeting-requests/{$flow06->id}/authority-initiated/finalize", ['agenda' => 'آجندا'])
            ->assertForbidden();
    }

    public function test_legitimate_business_actors_and_technical_admin_capability_remain_available(): void
    {
        [$users, $units, $employees] = $this->usersAndUnits();

        $this->actingAs($users['admin'], 'sanctum')->getJson('/api/admin/users')->assertOk();

        $flow01 = $this->prepareFlow01($users, $units, $employees);
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$flow01->id}/start-scheduling")
            ->assertOk()->assertJsonPath('data.status', 'SCHEDULING');

        $flow04 = $this->prepareFlow04Decision($users, $units, $employees);
        $this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$flow04->id}/authority/approve")
            ->assertOk()->assertJsonPath('data.status', 'HIGH_AUTHORITY_APPROVED');
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$flow04->id}/authority-scheduling/start")
            ->assertOk()->assertJsonPath('data.status', 'AUTHORITY_SCHEDULING');

        $flow06 = $this->createFlow06Draft($users, $units);
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$flow06->id}/authority-initiated/submit")
            ->assertOk();
        $this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$flow06->id}/authority-initiated/issue")
            ->assertOk()->assertJsonPath('data.status', 'ISSUED_BY_HIGH_AUTHORITY');
    }

    public function test_system_admin_is_denied_minutes_completion_verification_and_exception_application(): void
    {
        [$users, $units, $employees] = $this->usersAndUnits();

        [$meeting, $minutes, $decision] = $this->prepareMinutesFixture($users, $units, $employees);
        $minutesBefore = $minutes->fresh()->status;
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/meetings/{$meeting->id}/minutes/finalize")
            ->assertForbidden();
        $this->assertSame($minutesBefore, $minutes->fresh()->status);

        [$followUpMeeting, $followUpDecision] = $this->prepareFollowUpFixture($users, $units, $employees);
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meetings/{$followUpMeeting->id}/follow-up/initialize")->assertCreated();
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/decisions/{$followUpDecision->id}/follow-up/classify", ['classification' => 'ACTION_REQUIRED'])->assertOk();
        $itemId = $this->actingAs($users['exec'], 'sanctum')->postJson("/api/decisions/{$followUpDecision->id}/action-items", ['title' => 'اقدام امنیتی', 'responsible_unit_id' => $units['tech']->id, 'assigned_employee_id' => $users['director']->employee_id, 'due_date' => now()->addDays(5)->format('Y-m-d')])->assertCreated()->json('data.id');
        $this->actingAs($users['director'], 'sanctum')->postJson("/api/action-items/{$itemId}/start")->assertOk();
        $this->actingAs($users['director'], 'sanctum')->postJson("/api/action-items/{$itemId}/progress", ['progress_percent' => 100, 'note' => 'اقدام تکمیل شد'])->assertOk();
        $this->actingAs($users['director'], 'sanctum')->postJson("/api/action-items/{$itemId}/completion/submit", ['completion_note' => 'برای بررسی ارسال شد'])->assertOk()->assertJsonPath('data.status', 'PENDING_VERIFICATION');
        $completionBefore = MeetingActionItem::findOrFail($itemId)->status;
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/action-items/{$itemId}/completion/verify")
            ->assertForbidden();
        $this->assertSame($completionBefore, MeetingActionItem::findOrFail($itemId)->status);

        $exceptionMeeting = $this->prepareExceptionFixture($users, $units, $employees);
        $exceptionBefore = $exceptionMeeting->exceptionCases()->count();
        $this->actingAs($users['admin'], 'sanctum')->postJson("/api/meetings/{$exceptionMeeting->id}/exceptions", ['case_type' => 'POSTPONEMENT', 'reason' => 'تلاش غیرمجاز'])
            ->assertForbidden();
        $this->assertSame($exceptionBefore, $exceptionMeeting->fresh()->exceptionCases()->count());
    }

    private function prepareMinutesFixture(array $users, array $units, array $employees): array
    {
        $request = MeetingRequest::create(['request_number' => 'SEC-FLOW11-'.Str::random(8), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $units['tech']->id, 'created_by' => $users['exec']->id, 'subject' => 'آزمون امنیت FLOW-11', 'purpose' => 'REVIEW', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $users['exec']->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'SEC-FLOW11-M-'.Str::random(8), 'title' => $request->subject, 'organizing_unit_id' => $units['tech']->id, 'coordinator_id' => $users['exec']->id, 'chairperson_employee_id' => $employees['tech'], 'meeting_date' => now()->addDays(10)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار امنیت', 'status' => 'SCHEDULED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $units['tech']->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employees['tech'], 'organizational_unit_id' => $units['tech']->id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class);
        $scheduleVersion = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار امنیت'], $users['exec']->id);
        $schedule->applyToMeeting($meeting, $scheduleVersion);
        $participantVersion = app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $users['exec']->id);
        $session = MeetingExecutionSession::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'status' => 'ENDED', 'started_at' => now()->subHour(), 'started_by_user_id' => $users['exec']->id, 'ended_at' => now()->subMinutes(30), 'ended_by_user_id' => $users['exec']->id]);
        $minutes = MeetingMinutes::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'status' => MeetingMinutes::READY_TO_FINALIZE, 'created_by_user_id' => $users['exec']->id]);
        $version = MeetingMinuteVersion::create(['uuid' => (string) Str::uuid(), 'meeting_minutes_id' => $minutes->id, 'version_number' => 1, 'execution_session_id' => $session->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'summary' => 'خلاصهٔ امنیتی', 'prepared_by_user_id' => $users['exec']->id, 'submitted_at' => now()->subMinute()]);
        MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $version->id, 'sequence_number' => 1, 'decision_number' => 'SEC-001', 'decision_text' => 'تصمیم امنیتی', 'responsible_unit_id' => $units['tech']->id, 'responsible_employee_id' => $employees['tech'], 'target_date' => now()->addDays(5)->format('Y-m-d')]);
        $minutes->update(['current_version_id' => $version->id]);
        return [$meeting->fresh(), $minutes->fresh(), $version->decisions()->firstOrFail()];
    }

    private function prepareFollowUpFixture(array $users, array $units, array $employees): array
    {
        $request = MeetingRequest::create(['request_number' => 'SEC-FLOW12-'.Str::random(8), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $units['tech']->id, 'created_by' => $users['exec']->id, 'subject' => 'آزمون امنیت FLOW-12', 'purpose' => 'REVIEW', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $users['exec']->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'SEC-FLOW12-M-'.Str::random(8), 'title' => $request->subject, 'organizing_unit_id' => $units['tech']->id, 'coordinator_id' => $users['exec']->id, 'chairperson_employee_id' => $employees['tech'], 'meeting_date' => now()->subDay()->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار امنیت', 'status' => 'COMPLETED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $units['tech']->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employees['tech'], 'organizational_unit_id' => $units['tech']->id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class);
        $scheduleVersion = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار امنیت'], $users['exec']->id);
        $schedule->applyToMeeting($meeting, $scheduleVersion);
        $participantVersion = app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $users['exec']->id);
        $session = MeetingExecutionSession::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'status' => 'ENDED', 'started_at' => now()->subHour(), 'started_by_user_id' => $users['exec']->id, 'ended_at' => now()->subMinutes(30), 'ended_by_user_id' => $users['exec']->id]);
        $minutes = MeetingMinutes::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'status' => MeetingMinutes::FINALIZED, 'created_by_user_id' => $users['exec']->id, 'finalized_at' => now()->subMinutes(10), 'finalized_by_user_id' => $users['exec']->id]);
        $version = MeetingMinuteVersion::create(['uuid' => (string) Str::uuid(), 'meeting_minutes_id' => $minutes->id, 'version_number' => 1, 'execution_session_id' => $session->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'summary' => 'خلاصهٔ امنیتی', 'prepared_by_user_id' => $users['exec']->id, 'submitted_at' => now()->subMinutes(20)]);
        $decision = MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $version->id, 'sequence_number' => 1, 'decision_number' => 'SEC-002', 'decision_text' => 'تصمیم امنیتی', 'responsible_unit_id' => $units['tech']->id, 'responsible_employee_id' => $employees['tech'], 'target_date' => now()->addDays(5)->format('Y-m-d')]);
        $minutes->update(['current_version_id' => $version->id]);
        return [$meeting->fresh(), $decision->fresh()];
    }

    private function prepareExceptionFixture(array $users, array $units, array $employees): Meeting
    {
        $request = MeetingRequest::create(['request_number' => 'SEC-FLOW13-'.Str::random(8), 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $units['tech']->id, 'created_by' => $users['exec']->id, 'subject' => 'آزمون امنیت FLOW-13', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $users['exec']->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'SEC-FLOW13-M-'.Str::random(8), 'title' => $request->subject, 'organizing_unit_id' => $units['tech']->id, 'coordinator_id' => $users['exec']->id, 'chairperson_employee_id' => $employees['tech'], 'meeting_date' => now()->addDays(8)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار امنیت', 'status' => 'SCHEDULED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $units['tech']->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employees['tech'], 'organizational_unit_id' => $units['tech']->id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class)->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار امنیت'], $users['exec']->id);
        app(ScheduleVersionService::class)->applyToMeeting($meeting, $schedule);
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $users['exec']->id);
        return $meeting->fresh();
    }

    private function prepareFlow01(array $users, array $units, array $employees): MeetingRequest
    {
        $this->actingAs($users['exec'], 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id' => $units['tech']->id,
            'subject' => 'آزمون امنیت FLOW-01',
            'purpose' => 'COORDINATION',
            'description' => 'درخواست داخلی معتبر',
            'priority' => 'NORMAL',
            'participant_ids' => [$employees['tech']],
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();
        $this->actingAs($users['director'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk();
        return $request->fresh();
    }

    private function prepareFlow02(array $users, array $units, array $employees): MeetingRequest
    {
        $this->actingAs($users['exec'], 'sanctum')->postJson('/api/meeting-requests/inter-directorate', [
            'requesting_unit_id' => $units['tech']->id,
            'target_unit_id' => $units['finance']->id,
            'subject' => 'آزمون امنیت FLOW-02',
            'purpose' => 'COORDINATION',
            'description' => 'درخواست بین‌ریاستی معتبر',
            'priority' => 'NORMAL',
            'participant_ids' => [$employees['tech'], $employees['finance']],
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-inter-directorate")->assertOk();
        $this->actingAs($users['director'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/requester-approve")->assertOk();
        $this->actingAs($users['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-review")->assertOk();
        $this->actingAs($users['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-approve")->assertOk();
        return $request->fresh();
    }

    private function prepareFlow03(array $users, array $units, array $employees): MeetingRequest
    {
        $this->actingAs($users['exec'], 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $units['tech']->id,
            'target_unit_ids' => [$units['finance']->id, $units['hr']->id],
            'subject' => 'آزمون امنیت FLOW-03',
            'purpose' => 'COORDINATION',
            'description' => 'درخواست چندریاستی معتبر',
            'priority' => 'NORMAL',
            'participant_ids' => [$employees['tech'], $employees['finance'], $employees['hr']],
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-multi")->assertOk();
        $this->actingAs($users['director'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/requester-approve")->assertOk();
        foreach ([[$units['finance'], $users['financeExec'], $users['financeDirector']], [$units['hr'], $users['hrExec'], $users['hrDirector']]] as [$unit, $exec, $director]) {
            $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$unit->id}/review")->assertOk();
            $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$unit->id}/approve")->assertOk();
        }
        return $request->fresh();
    }

    private function prepareFlow04Decision(array $users, array $units, array $employees): MeetingRequest
    {
        $this->actingAs($users['exec'], 'sanctum')->postJson('/api/meeting-requests/high-authority', [
            'requesting_unit_id' => $units['tech']->id,
            'subject' => 'آزمون امنیت FLOW-04',
            'purpose' => 'DECISION',
            'meeting_justification' => 'توجیه معتبر',
            'requested_outcome' => 'نتیجهٔ معتبر',
            'priority' => 'HIGH',
            'participant_ids' => [$employees['tech']],
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($users['exec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-high-authority")->assertOk();
        $this->actingAs($users['director'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/high-authority/requester-approve")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/review")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-office/forward")->assertOk();
        return $request->fresh();
    }

    private function createFlow06Draft(array $users, array $units): MeetingRequest
    {
        $this->actingAs($users['office'], 'sanctum')->postJson('/api/meeting-requests/authority-initiated', [
            'subject' => 'آزمون امنیت FLOW-06',
            'purpose' => 'DECISION',
            'directive' => 'متن دستور معتبر',
            'description' => 'شرح رسمی دستور',
            'meeting_justification' => 'هماهنگی معتبر میان ریاست‌ها',
            'requested_outcome' => 'نتیجهٔ معتبر',
            'proposed_agenda' => 'آجندای معتبر',
            'priority' => 'HIGH',
            'estimated_duration_minutes' => 60,
            'preferred_location' => 'تالار رسمی',
            'participating_unit_ids' => [$units['tech']->id],
        ])->assertCreated();
        return MeetingRequest::latest('id')->firstOrFail();
    }

    private function prepareFlow06Scheduling(array $users, array $units, array $employees): MeetingRequest
    {
        $request = $this->createFlow06Draft($users, $units);
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/submit")->assertOk();
        $this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/issue")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/distribute")->assertOk();
        $this->actingAs($users['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['tech']->id}/acknowledge")->assertOk();
        return $request->fresh();
    }

    private function usersAndUnits(): array
    {
        $user = fn (string $email) => User::where('email', $email)->firstOrFail();
        $unit = fn (string $code) => OrganizationalUnit::where('code', $code)->firstOrFail();
        $employee = fn (string $number) => Employee::where('employee_number', $number)->firstOrFail();

        return [
            [
                'admin' => $user('system.admin@example.test'),
                'exec' => $user('tech.exec@example.test'),
                'director' => $user('tech.director@example.test'),
                'office' => $user('authority.office@example.test'),
                'secretary' => $user('executive.secretary@example.test'),
                'authority' => $user('high.authority@example.test'),
                'financeExec' => $user('finance.exec@example.test'),
                'financeDirector' => $user('finance.director@example.test'),
                'hrExec' => $user('hr.exec@example.test'),
                'hrDirector' => $user('hr.director@example.test'),
                'techDirector' => $user('tech.director@example.test'),
            ],
            [
                'tech' => $unit('TECH'),
                'finance' => $unit('FIN'),
                'hr' => $unit('HR'),
            ],
            [
                'tech' => $employee('EMP-TECH-003')->id,
                'finance' => $employee('EMP-FIN-002')->id,
                'hr' => $employee('EMP-HR-002')->id,
                'authority' => $employee('EMP-AUTH-001')->id,
            ],
        ];
    }
}
