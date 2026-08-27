<?php

namespace Tests\Feature;

use App\Actions\Terminals\RegisterTerminal;
use App\Actions\Meetings\AssignTerminalToMeeting;
use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Support\BiometricCandidateSetBuilder;
use App\Support\TerminalManifestBuilder;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BiometricCandidateSetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_candidate_set_is_bound_to_current_participant_version_and_excludes_nonactive_templates_and_bytes(): void
    {
        [$meeting, $employee, $actor] = $this->meetingFixture();
        $terminal = app(RegisterTerminal::class)->execute(User::query()->where('email', 'system.admin@example.test')->firstOrFail(), [
            'organization_id' => $this->unit('TECH')->organization_id, 'code' => 'FLOW10A-CAND-'.uniqid(), 'name' => 'ترمینال نامزدها', 'status' => 'ACTIVE',
        ]);
        $assignment = app(AssignTerminalToMeeting::class)->execute($actor, $meeting, $terminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(30)->toIso8601String());
        $session = TerminalMeetingSession::create([
            'uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'terminal_id' => $terminal->id, 'meeting_id' => $meeting->id,
            'schedule_version_id' => $meeting->current_schedule_version_id, 'participant_version_id' => $meeting->current_participant_version_id,
            'status' => 'ACTIVE', 'session_token_hash' => hash('sha256', 'candidate-session'), 'activated_at' => now(), 'expires_at' => now()->addHour(),
        ]);
        $active = EmployeeBiometricTemplate::create($this->templateData($employee, $actor, $terminal, 'RIGHT_INDEX', 'ACTIVE_TEMPLATE'));
        EmployeeBiometricTemplate::create($this->templateData($employee, $actor, $terminal, 'LEFT_INDEX', 'REVOKED_TEMPLATE', 'REVOKED'));

        $payload = app(BiometricCandidateSetBuilder::class)->build($session);
        $this->assertSame(1, $payload['candidate_count'] ?? count($payload['candidates']));
        $this->assertSame(1, $payload['biometric_ready']);
        $this->assertSame($active->uuid, $payload['candidates'][0]['template_uuid']);
        $this->assertSame('ACTIVE_TEMPLATE', $payload['candidates'][0]['template_data']);
        $this->assertSame(hash('sha256', 'ACTIVE_TEMPLATE'), $payload['candidates'][0]['template_checksum']);
        $this->assertSame($payload['candidate_set_checksum'], app(BiometricCandidateSetBuilder::class)->build($session)['candidate_set_checksum']);
        $manifest = app(TerminalManifestBuilder::class)->build($session);
        $this->assertStringNotContainsString('template_data', json_encode($manifest, JSON_UNESCAPED_SLASHES));
        $this->withHeader('Authorization', 'Bearer candidate-session')
            ->getJson('/api/terminal/v1/sessions/'.$session->uuid.'/biometric-candidates')
            ->assertOk()
            ->assertJsonPath('data.candidates.0.template_data', 'ACTIVE_TEMPLATE');
    }

    public function test_schedule_change_rejects_old_candidate_session(): void
    {
        [$meeting, $employee, $actor] = $this->meetingFixture();
        $terminal = app(RegisterTerminal::class)->execute(User::query()->where('email', 'system.admin@example.test')->firstOrFail(), ['organization_id' => $this->unit('TECH')->organization_id, 'code' => 'FLOW10A-STALE-S-'.uniqid(), 'name' => 'ترمینال stale schedule', 'status' => 'ACTIVE']);
        $assignment = app(AssignTerminalToMeeting::class)->execute($actor, $meeting, $terminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(30)->toIso8601String());
        $session = TerminalMeetingSession::create(['uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'terminal_id' => $terminal->id, 'meeting_id' => $meeting->id, 'schedule_version_id' => $meeting->current_schedule_version_id, 'participant_version_id' => $meeting->current_participant_version_id, 'status' => 'ACTIVE', 'session_token_hash' => hash('sha256', Str::random(32)), 'activated_at' => now(), 'expires_at' => now()->addHour()]);
        $schedule = app(ScheduleVersionService::class); $schedule->applyToMeeting($meeting->fresh(), $schedule->createNext($meeting->fresh(), ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '12:00', 'end_time' => '13:00', 'location' => 'تغییر زمان'], $actor->id));
        try { app(BiometricCandidateSetBuilder::class)->build($session); $this->fail('stale schedule session was accepted'); } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { $this->assertSame(422, $e->getResponse()->getStatusCode()); $this->assertSame('TERMINAL_SESSION_STALE', $e->getResponse()->getData(true)['code']); }
    }

    public function test_participant_change_rejects_old_candidate_session(): void
    {
        [$meeting, $employee, $actor] = $this->meetingFixture();
        $terminal = app(RegisterTerminal::class)->execute(User::query()->where('email', 'system.admin@example.test')->firstOrFail(), ['organization_id' => $this->unit('TECH')->organization_id, 'code' => 'FLOW10A-STALE-P-'.uniqid(), 'name' => 'ترمینال stale participant', 'status' => 'ACTIVE']);
        $assignment = app(AssignTerminalToMeeting::class)->execute($actor, $meeting, $terminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(30)->toIso8601String());
        $session = TerminalMeetingSession::create(['uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'terminal_id' => $terminal->id, 'meeting_id' => $meeting->id, 'schedule_version_id' => $meeting->current_schedule_version_id, 'participant_version_id' => $meeting->current_participant_version_id, 'status' => 'ACTIVE', 'session_token_hash' => hash('sha256', Str::random(32)), 'activated_at' => now(), 'expires_at' => now()->addHour()]);
        $new = \App\Models\MeetingParticipantVersion::create(['meeting_id' => $meeting->id, 'version_number' => 2, 'source_change_case_id' => null, 'created_by_user_id' => $actor->id, 'created_at' => now()]);
        foreach ($meeting->currentParticipantVersion->members as $member) \App\Models\MeetingParticipantVersionMember::create(['participant_version_id' => $new->id, 'employee_id' => $member->employee_id, 'organizational_unit_id' => $member->organizational_unit_id, 'participant_role' => $member->participant_role, 'owner_type' => $member->owner_type, 'owner_unit_id' => $member->owner_unit_id, 'is_mandatory' => $member->is_mandatory, 'created_at' => now()]);
        $meeting->forceFill(['current_participant_version_id' => $new->id])->save();
        try { app(BiometricCandidateSetBuilder::class)->build($session); $this->fail('stale participant session was accepted'); } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { $this->assertSame(422, $e->getResponse()->getStatusCode()); $this->assertSame('TERMINAL_SESSION_STALE', $e->getResponse()->getData(true)['code']); }
    }

    private function templateData(Employee $employee, User $actor, $terminal, string $finger, string $bytes, string $status = 'ACTIVE'): array
    {
        return ['uuid' => (string) Str::uuid(), 'organization_id' => $terminal->organization_id, 'employee_id' => $employee->id, 'modality' => 'FINGERPRINT', 'finger_position' => $finger, 'template_vendor' => 'FAKE', 'template_format' => 'SIMULATOR-V1', 'algorithm_version' => 'SIMULATOR-V1', 'template_data' => $bytes, 'template_checksum' => hash('sha256', $bytes), 'quality_score' => 95, 'version_number' => $finger === 'RIGHT_INDEX' ? 1 : 1, 'status' => $status, 'source_terminal_id' => $terminal->id, 'enrollment_session_id' => null, 'enrolled_by_user_id' => $actor->id, 'enrolled_at' => now()];
    }

    private function meetingFixture(): array
    {
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail(); $unit = $this->unit('TECH'); $employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'FLOW10A-C-'.uniqid(), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $actor->id, 'subject' => 'FLOW-10A candidate set', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $actor->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW10A-C-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $actor->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->addDays(20)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW-10A', 'status' => 'SCHEDULED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']); MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class); $schedule->applyToMeeting($meeting, $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $actor->id)); app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
        return [$meeting->fresh(), $employee, $actor];
    }

    private function unit(string $code) { return \App\Models\OrganizationalUnit::query()->where('code', $code)->firstOrFail(); }
}
