<?php

namespace Tests\Feature;

use App\Actions\Biometrics\StartBiometricEnrollment;
use App\Actions\Meetings\AssignTerminalToMeeting;
use App\Actions\Terminals\ActivateTerminalMeetingSession;
use App\Actions\Terminals\IssueTerminalCredential;
use App\Actions\Terminals\RegisterTerminal;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\Organization;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\ScopedCapabilityManager;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use App\Support\SpecialCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TerminalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_active_meeting_session_is_sufficient_for_participant_fingerprint_capture(): void
    {
        [$meeting, $employee, $coordinator] = $this->meetingFixture();
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $terminal = app(RegisterTerminal::class)->execute($admin, [
            'organization_id' => $meeting->units()->with('unit')->firstOrFail()->unit->organization_id,
            'code' => 'FLOW10A-BUSY-'.uniqid(), 'name' => 'ترمینال مصروف FLOW-10A', 'manufacturer' => 'FAKE', 'model' => 'SIMULATOR', 'platform' => 'TEST', 'status' => 'ACTIVE',
        ]);
        app(IssueTerminalCredential::class)->execute($admin, $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($coordinator, $meeting, $terminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(30)->toIso8601String());
        $activation = app(ActivateTerminalMeetingSession::class)->execute($terminal, $assignment);
        $session = $activation['session'];

        $path = '/api/terminal/v1/sessions/'.$session->uuid.'/biometric-templates';
        $payload = [
            'employee_id' => $employee->id,
            'finger_position' => 'RIGHT_THUMB',
            'template_vendor' => 'SIMULATOR',
            'template_format' => 'TEST-V1',
            'algorithm_version' => 'TEST-V1',
            'template_data' => base64_encode('meeting-scoped-template'),
            'template_encoding' => 'BASE64',
        ];

        $this->postJson($path, $payload)->assertUnauthorized();

        $response = $this->withHeader('Authorization', 'Bearer '.$activation['session_token'])
            ->postJson($path, $payload)
            ->assertOk()
            ->assertJsonPath('data.progress.employee_id', $employee->id)
            ->assertJsonPath('data.progress.active_finger_count', 1)
            ->assertJsonPath('data.progress.required_finger_count', 2)
            ->assertJsonPath('data.template.terminal_meeting_session_id', $session->id);

        $this->assertDatabaseHas('employee_biometric_templates', [
            'employee_id' => $employee->id,
            'finger_position' => 'RIGHT_THUMB',
            'terminal_meeting_session_id' => $session->id,
            'enrollment_session_id' => null,
            'enrolled_by_user_id' => null,
        ]);
        $this->assertDatabaseCount('biometric_enrollment_sessions', 0);
        $this->assertDatabaseHas('terminal_audit_events', [
            'action' => 'MEETING_BIOMETRIC_TEMPLATE_ENROLLED',
            'session_id' => $session->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$activation['session_token'])
            ->postJson($path, [...$payload, 'finger_position' => 'LEFT_THUMB'])
            ->assertOk()
            ->assertJsonPath('data.progress.active_finger_count', 2)
            ->assertJsonPath('data.progress.required_finger_count', 2);

        $this->withHeader('Authorization', 'Bearer '.$activation['session_token'])
            ->postJson($path, [...$payload, 'finger_position' => 'RIGHT_INDEX'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'MEETING_BIOMETRIC_ENROLLMENT_COMPLETE');

        app(ScopedCapabilityManager::class)->grant(
            $coordinator,
            SpecialCapability::BIOMETRIC_ENROLLMENT,
            $this->unit('TECH'),
            $admin
        );
        $this->actingAs($coordinator, 'sanctum')
            ->postJson('/api/biometric/enrollment-sessions', ['employee_id' => $employee->id, 'terminal_id' => $terminal->id])
            ->assertStatus(422)->assertJsonPath('code', 'TERMINAL_BUSY');

        $this->withHeader('Authorization', 'Bearer '.$activation['session_token'])
            ->postJson($path, $payload)
            ->assertStatus(409)
            ->assertJsonPath('code', 'BIOMETRIC_TEMPLATE_STALE');
    }

    public function test_meeting_session_cannot_enroll_an_employee_outside_its_participant_snapshot(): void
    {
        [$meeting, , $coordinator] = $this->meetingFixture();
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $terminal = app(RegisterTerminal::class)->execute($admin, [
            'organization_id' => $meeting->units()->with('unit')->firstOrFail()->unit->organization_id,
            'code' => 'FLOW10A-NONPARTICIPANT-'.uniqid(),
            'name' => 'Meeting attendance terminal',
            'manufacturer' => 'FAKE',
            'model' => 'SIMULATOR',
            'platform' => 'TEST',
            'status' => 'ACTIVE',
        ]);
        app(IssueTerminalCredential::class)->execute($admin, $terminal);
        $assignment = app(AssignTerminalToMeeting::class)->execute($coordinator, $meeting, $terminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(30)->toIso8601String());
        $activation = app(ActivateTerminalMeetingSession::class)->execute($terminal, $assignment);
        $outsideParticipant = Employee::query()->where('email', 'finance.director@example.test')->firstOrFail();

        $this->withHeader('Authorization', 'Bearer '.$activation['session_token'])
            ->postJson('/api/terminal/v1/sessions/'.$activation['session']->uuid.'/biometric-templates', [
                'employee_id' => $outsideParticipant->id,
                'finger_position' => 'RIGHT_THUMB',
                'template_vendor' => 'SIMULATOR',
                'template_format' => 'TEST-V1',
                'algorithm_version' => 'TEST-V1',
                'template_data' => 'SAFE_TEMPLATE',
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'BIOMETRIC_MATCH_EMPLOYEE_NOT_PARTICIPANT');

        $this->assertDatabaseMissing('employee_biometric_templates', [
            'employee_id' => $outsideParticipant->id,
            'terminal_meeting_session_id' => $activation['session']->id,
        ]);
    }

    public function test_scoped_enrollment_operator_can_start_session_for_terminal_and_terminal_receives_that_context(): void
    {
        $employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $actor->givePermissionTo('biometric.enroll');
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $terminal = app(RegisterTerminal::class)->execute($admin, [
            'organization_id' => $this->unit('TECH')->organization_id,
            'code' => 'ENROLL-CONTEXT-'.uniqid(),
            'name' => 'SVT3000 enrollment terminal',
            'manufacturer' => 'DERMALOG',
            'model' => 'SVT3000 SE',
            'status' => 'ACTIVE',
        ]);
        $otherOrganization = Organization::create([
            'name' => 'Organization outside biometric scope',
            'short_name' => 'OUTSIDE',
            'type' => 'GOVERNMENT',
            'status' => 'ACTIVE',
        ]);
        $otherOrganizationTerminal = app(RegisterTerminal::class)->execute($admin, [
            'organization_id' => $otherOrganization->id,
            'code' => 'ENROLL-OTHER-'.uniqid(),
            'name' => 'Out-of-scope terminal',
            'status' => 'ACTIVE',
        ]);
        app(ScopedCapabilityManager::class)->grant($actor, SpecialCapability::BIOMETRIC_ENROLLMENT, $this->unit('TECH'), $admin);

        $this->getJson('/api/employees/'.$employee->id.'/biometric-enrollment-context')->assertUnauthorized();

        $context = $this->actingAs($actor, 'sanctum')
            ->getJson('/api/employees/'.$employee->id.'/biometric-enrollment-context')
            ->assertOk()
            ->assertJsonCount(1, 'data.terminals')
            ->assertJsonPath('data.terminals.0.id', $terminal->id)
            ->assertJsonMissing(['id' => $otherOrganizationTerminal->id]);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/biometric/enrollment-sessions', [
                'employee_id' => $employee->id,
                'terminal_id' => $otherOrganizationTerminal->id,
            ])
            ->assertForbidden()
            ->assertJsonPath('code', 'BIOMETRIC_ENROLLMENT_TERMINAL_SCOPE_MISMATCH');

        $startedEnrollment = $this->actingAs($actor, 'sanctum')
            ->postJson('/api/biometric/enrollment-sessions', [
                'employee_id' => $employee->id,
                'terminal_id' => $context->json('data.terminals.0.id'),
            ])
            ->assertOk()
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.terminal_id', $terminal->id)
            ->json('data');

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/employees/'.$employee->id.'/biometric-enrollment-context')
            ->assertOk()
            ->assertJsonPath('data.enrollment.uuid', $startedEnrollment['uuid'])
            ->assertJsonPath('data.enrollment.terminal.id', $terminal->id);

        $credential = app(IssueTerminalCredential::class)->execute($admin, $terminal);
        $this->withHeader('Authorization', 'Bearer '.$credential['token'])
            ->getJson('/api/terminal/v1/enrollment/current')
            ->assertOk()
            ->assertJsonPath('data.enrollment.employee_id', $employee->id)
            ->assertJsonPath('data.enrollment.terminal_id', $terminal->id)
            ->assertJsonPath('data.enrollment.required_finger_count', 2)
            ->assertJsonPath('data.enrollment.active_finger_count', 0)
            ->assertJsonPath('data.enrollment.active_finger_positions', []);
    }

    public function test_biometric_enrollment_context_obeys_employee_scope(): void
    {
        $employee = Employee::query()->where('email', 'finance.director@example.test')->firstOrFail();
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $actor->givePermissionTo('biometric.enroll');
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        app(ScopedCapabilityManager::class)->grant($actor, SpecialCapability::BIOMETRIC_ENROLLMENT, $this->unit('TECH'), $admin);

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/employees/'.$employee->id.'/biometric-enrollment-context')
            ->assertForbidden();
    }

    public function test_raw_biometric_image_is_rejected_and_terminal_enrollment_isolation_holds(): void
    {
        $admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $employeeA = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $employeeB = Employee::query()->where('email', 'finance.director@example.test')->firstOrFail();
        $terminalA = app(RegisterTerminal::class)->execute($admin, ['organization_id' => $this->unit('TECH')->organization_id, 'code' => 'FLOW10A-ISO-A-'.uniqid(), 'name' => 'ترمینال ایزوله A', 'status' => 'ACTIVE']);
        $terminalB = app(RegisterTerminal::class)->execute($admin, ['organization_id' => $this->unit('TECH')->organization_id, 'code' => 'FLOW10A-ISO-B-'.uniqid(), 'name' => 'ترمینال ایزوله B', 'status' => 'ACTIVE']);
        $credentialA = app(IssueTerminalCredential::class)->execute($admin, $terminalA);

        // Submission revalidates the enrollment operator's CURRENT authority. The isolation fixture
        // therefore needs legitimate delegated capability scopes for both employees; the
        // wrong-terminal assertion must fail because of terminal isolation, not authorization.
        $manager = app(ScopedCapabilityManager::class);
        foreach ([$this->unit('TECH'), $this->unit('FIN')] as $unit) {
            $manager->grant($actor, SpecialCapability::BIOMETRIC_ENROLLMENT, $unit, $admin);
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

        $this->withHeader('Authorization', 'Bearer '.$credentialA['token'])
            ->postJson('/api/terminal/v1/enrollment/'.$enrollmentA->uuid.'/template', [
                'finger_position' => 'RIGHT_INDEX',
                'template_vendor' => 'FAKE',
                'template_format' => 'SIMULATOR-V1',
                'algorithm_version' => 'SIMULATOR-V1',
                'template_data' => 'SAFE_TEMPLATE',
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$credentialA['token'])
            ->getJson('/api/terminal/v1/enrollment/current')
            ->assertOk()
            ->assertJsonPath('data.enrollment.active_finger_count', 1)
            ->assertJsonPath('data.enrollment.active_finger_positions', ['RIGHT_INDEX']);
    }

    private function unit(string $code)
    {
        return OrganizationalUnit::query()->where('code', $code)->firstOrFail();
    }

    private function meetingFixture(): array
    {
        $coordinator = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $unit = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
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
