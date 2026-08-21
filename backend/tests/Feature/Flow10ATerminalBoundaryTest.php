<?php

namespace Tests\Feature;

use App\Actions\Meetings\AssignTerminalToMeeting;
use App\Actions\Terminals\ActivateTerminalMeetingSession;
use App\Actions\Terminals\IssueTerminalCredential;
use App\Actions\Terminals\RegisterTerminal;
use App\Actions\Biometrics\StartBiometricEnrollment;
use App\Models\AmmsRoleScope;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Flow10ATerminalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_active_meeting_session_blocks_biometric_enrollment(): void
    {
        [$meeting, $employee, $coordinator] = $this->meetingFixture();
        $terminal = app(RegisterTerminal::class)->execute(User::query()->where('email', 'system.admin@example.test')->firstOrFail(), [
            'organization_id' => $meeting->units()->with('unit')->firstOrFail()->unit->organization_id,
            'code' => 'FLOW10A-BUSY-'.uniqid(), 'name' => 'ترمینال مصروف FLOW-10A', 'manufacturer' => 'FAKE', 'model' => 'SIMULATOR', 'platform' => 'TEST', 'status' => 'ACTIVE',
        ]);
        app(IssueTerminalCredential::class)->execute(User::query()->where('email', 'system.admin@example.test')->firstOrFail(), $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($coordinator, $meeting, $terminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(30)->toIso8601String());
        app(ActivateTerminalMeetingSession::class)->execute($terminal, $assignment);

        $role = Role::findByName('BIOMETRIC_ENROLLMENT_OFFICER', 'sanctum');
        $coordinator->assignRole($role);
        AmmsRoleScope::create(['user_id' => $coordinator->id, 'role_id' => $role->id, 'organizational_unit_id' => $employee->organizational_unit_id]);
        $this->actingAs($coordinator, 'sanctum')
            ->postJson('/api/biometric/enrollment-sessions', ['employee_id' => $employee->id, 'terminal_id' => $terminal->id])
            ->assertStatus(422)->assertJsonPath('code', 'TERMINAL_BUSY');
    }

    public function test_raw_biometric_image_is_rejected_and_terminal_enrollment_isolation_holds(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $employeeA = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $employeeB = Employee::query()->where('email', 'finance.director@example.test')->firstOrFail();
        $terminalA = app(RegisterTerminal::class)->execute($admin, ['organization_id' => $this->unit('TECH')->organization_id, 'code' => 'FLOW10A-ISO-A-'.uniqid(), 'name' => 'ترمینال ایزوله A', 'status' => 'ACTIVE']);
        $terminalB = app(RegisterTerminal::class)->execute($admin, ['organization_id' => $this->unit('TECH')->organization_id, 'code' => 'FLOW10A-ISO-B-'.uniqid(), 'name' => 'ترمینال ایزوله B', 'status' => 'ACTIVE']);
        $credentialA = app(\App\Actions\Terminals\IssueTerminalCredential::class)->execute($admin, $terminalA);

        // Submission revalidates the enrollment operator's CURRENT authority. The isolation fixture
        // therefore needs legitimate scopes for both employees; the wrong-terminal assertion must
        // fail because of terminal isolation, not because the test actor was never authorized.
        $role = Role::findByName('BIOMETRIC_ENROLLMENT_OFFICER', 'sanctum');
        $actor->assignRole($role);
        foreach ([$this->unit('TECH')->id, $this->unit('FIN')->id] as $unitId) {
            AmmsRoleScope::firstOrCreate(['user_id' => $actor->id, 'role_id' => $role->id, 'organizational_unit_id' => $unitId]);
        }

        $enrollmentA = app(StartBiometricEnrollment::class)->execute($actor, $employeeA, $terminalA);
        $enrollmentB = app(StartBiometricEnrollment::class)->execute($actor, $employeeB, $terminalB);

        $this->withHeader('Authorization', 'Bearer '.$credentialA['token'])
            ->postJson('/api/terminal/v1/enrollment/'.$enrollmentA->uuid.'/template', ['finger_position' => 'RIGHT_INDEX', 'template_vendor' => 'FAKE', 'template_format' => 'SIMULATOR-V1', 'algorithm_version' => 'SIMULATOR-V1', 'template_data' => 'SAFE_TEMPLATE', 'fingerprint_image' => 'RAW_BYTES'])
            ->assertStatus(422)->assertJsonPath('code', 'RAW_BIOMETRIC_IMAGE_NOT_ACCEPTED');

        $this->withHeader('Authorization', 'Bearer '.$credentialA['token'])
            ->postJson('/api/terminal/v1/enrollment/'.$enrollmentA->uuid.'/template', ['finger_position' => 'RIGHT_INDEX', 'template_vendor' => 'FAKE', 'template_format' => 'SIMULATOR-V1', 'algorithm_version' => 'SIMULATOR-V1', 'template_data' => 'SAFE_TEMPLATE', 'quality_accepted' => false])
            ->assertStatus(422)->assertJsonPath('code', 'FINGERPRINT_QUALITY_TOO_LOW');

        $this->withHeader('Authorization', 'Bearer '.$credentialA['token'])
            ->postJson('/api/terminal/v1/enrollment/'.$enrollmentB->uuid.'/template', ['finger_position' => 'RIGHT_INDEX', 'template_vendor' => 'FAKE', 'template_format' => 'SIMULATOR-V1', 'algorithm_version' => 'SIMULATOR-V1', 'template_data' => 'SAFE_TEMPLATE'])
            ->assertNotFound()->assertJsonPath('code', 'BIOMETRIC_ENROLLMENT_SESSION_NOT_FOUND');
    }

    private function unit(string $code) { return \App\Models\OrganizationalUnit::query()->where('code', $code)->firstOrFail(); }

    private function meetingFixture(): array
    {
        $coordinator = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $unit = \App\Models\OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'FLOW10A-B-'.uniqid(), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $coordinator->id, 'subject' => 'FLOW-10A terminal boundary', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $coordinator->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW10A-B-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $coordinator->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->addDays(20)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW-10A', 'status' => 'SCHEDULED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class);
        $schedule->applyToMeeting($meeting, $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $coordinator->id));
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $coordinator->id);
        return [$meeting->fresh(), $employee, $coordinator];
    }
}
