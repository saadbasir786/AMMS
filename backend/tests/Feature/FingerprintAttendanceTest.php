<?php

namespace Tests\Feature;

use App\Actions\Attendance\RecordFingerprintAttendance;
use App\Actions\Biometrics\RevokeFingerprintTemplate;
use App\Actions\Meetings\AssignTerminalToMeeting;
use App\Actions\Terminals\RegisterTerminal;
use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Support\BiometricCandidateSetBuilder;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FingerprintAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_valid_simulated_fingerprint_is_idempotent_and_duplicate_employee_is_rejected(): void
    {
        [$meeting, $employee, $actor, $session, $template] = $this->fixture();
        $package = app(BiometricCandidateSetBuilder::class)->build($session);
        $event = (string) Str::uuid();
        $data = ['client_event_uuid' => $event, 'employee_id' => $employee->id, 'matched_template_uuid' => $template->uuid, 'finger_position' => 'RIGHT_INDEX', 'matcher_vendor' => 'FAKE', 'matcher_algorithm_version' => 'SIMULATOR-V1', 'match_score' => 99.0, 'threshold_reference' => 'FAKE_ACCEPTED', 'candidate_set_checksum' => $package['candidate_set_checksum'], 'device_captured_at' => now()->subSeconds(4)];
        $first = app(RecordFingerprintAttendance::class)->execute($session, $data);
        $retry = app(RecordFingerprintAttendance::class)->execute($session, $data);
        $this->assertSame($first->id, $retry->id);
        $this->assertSame('FINGERPRINT', $first->attendance_method);
        $this->assertNotNull($first->recorded_at_server);
        $this->assertDatabaseCount('meeting_attendance_records', 1);

        $data['client_event_uuid'] = (string) Str::uuid();
        try { app(RecordFingerprintAttendance::class)->execute($session, $data); $this->fail('duplicate employee attendance was accepted'); } catch (\Throwable $e) { $this->assertTrue(method_exists($e, 'getResponse')); $this->assertSame(409, $e->getResponse()->getStatusCode()); }
    }

    public function test_revoked_template_is_rejected_but_historical_attendance_remains(): void
    {
        [$meeting, $employee, $actor, $session, $template] = $this->fixture();
        $package = app(BiometricCandidateSetBuilder::class)->build($session);
        app(RevokeFingerprintTemplate::class)->execute($actor, $template, 'آزمایش ابطال قبل از حضور');
        try { app(RecordFingerprintAttendance::class)->execute($session, ['client_event_uuid' => (string) Str::uuid(), 'employee_id' => $employee->id, 'matched_template_uuid' => $template->uuid, 'candidate_set_checksum' => $package['candidate_set_checksum']]); $this->fail('revoked template was accepted'); } catch (\Throwable $e) { $this->assertTrue(method_exists($e, 'getResponse')); $this->assertSame(422, $e->getResponse()->getStatusCode()); $this->assertSame('BIOMETRIC_TEMPLATE_REVOKED', $e->getResponse()->getData(true)['code']); }

        [$meeting2, $employee2, $actor2, $session2, $template2] = $this->fixture(true);
        $package2 = app(BiometricCandidateSetBuilder::class)->build($session2);
        app(RecordFingerprintAttendance::class)->execute($session2, ['client_event_uuid' => (string) Str::uuid(), 'employee_id' => $employee2->id, 'matched_template_uuid' => $template2->uuid, 'candidate_set_checksum' => $package2['candidate_set_checksum']]);
        app(RevokeFingerprintTemplate::class)->execute($actor2, $template2, 'ابطال پس از حضور');
        $this->assertDatabaseHas('meeting_attendance_records', ['meeting_id' => $meeting2->id, 'employee_id' => $employee2->id]);
    }

    public function test_wrong_checksum_and_wrong_employee_template_pair_are_rejected(): void
    {
        [$meeting, $employee, $actor, $session, $template] = $this->fixture();
        $package = app(BiometricCandidateSetBuilder::class)->build($session);
        $base = ['client_event_uuid' => (string) Str::uuid(), 'employee_id' => $employee->id, 'matched_template_uuid' => $template->uuid, 'candidate_set_checksum' => str_repeat('0', 64)];
        try { app(RecordFingerprintAttendance::class)->execute($session, $base); $this->fail('wrong checksum was accepted'); } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { $this->assertSame(422, $e->getResponse()->getStatusCode()); }

        $other = Employee::query()->where('email', 'finance.director@example.test')->firstOrFail();
        $tryNonParticipant = ['client_event_uuid' => (string) Str::uuid(), 'employee_id' => $other->id, 'matched_template_uuid' => $template->uuid, 'candidate_set_checksum' => $package['candidate_set_checksum']];
        try { app(RecordFingerprintAttendance::class)->execute($session, $tryNonParticipant); $this->fail('nonparticipant was accepted'); } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { $this->assertSame(422, $e->getResponse()->getStatusCode()); $this->assertSame('BIOMETRIC_MATCH_EMPLOYEE_NOT_PARTICIPANT', $e->getResponse()->getData(true)['code']); }
        $otherTemplate = EmployeeBiometricTemplate::create($this->templateData($other, $actor, $session->terminal));
        $base['client_event_uuid'] = (string) Str::uuid(); $base['candidate_set_checksum'] = $package['candidate_set_checksum']; $base['employee_id'] = $employee->id; $base['matched_template_uuid'] = $otherTemplate->uuid;
        try { app(RecordFingerprintAttendance::class)->execute($session, $base); $this->fail('wrong employee/template pair was accepted'); } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { $this->assertSame(422, $e->getResponse()->getStatusCode()); }
    }

    private function fixture(bool $secondVersion = false): array
    {
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail(); $unit = \App\Models\OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail(); $employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'FLOW10A-F-'.uniqid(), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $actor->id, 'subject' => 'FLOW-10A fingerprint attendance', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $actor->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW10A-F-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $actor->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->addDays(20)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW-10A', 'status' => 'SCHEDULED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']); MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class); $schedule->applyToMeeting($meeting, $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $actor->id)); app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id); $meeting = $meeting->fresh();
        $terminal = app(RegisterTerminal::class)->execute(User::query()->where('email', 'system.admin@example.test')->firstOrFail(), ['organization_id' => $unit->organization_id, 'code' => 'FLOW10A-F-'.uniqid(), 'name' => 'ترمینال حضور', 'status' => 'ACTIVE']);
        $assignment = app(AssignTerminalToMeeting::class)->execute($actor, $meeting, $terminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(30)->toIso8601String());
        $session = TerminalMeetingSession::create(['uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'terminal_id' => $terminal->id, 'meeting_id' => $meeting->id, 'schedule_version_id' => $meeting->current_schedule_version_id, 'participant_version_id' => $meeting->current_participant_version_id, 'status' => 'ACTIVE', 'session_token_hash' => hash('sha256', Str::random(32)), 'activated_at' => now(), 'expires_at' => now()->addHour()]);
        $template = EmployeeBiometricTemplate::create($this->templateData($employee, $actor, $terminal, $secondVersion ? 2 : 1));
        return [$meeting, $employee, $actor, $session, $template];
    }

    private function templateData(Employee $employee, User $actor, $terminal, int $version = 1): array
    {
        $bytes = 'ACTIVE_ATTENDANCE_TEMPLATE'; return ['uuid' => (string) Str::uuid(), 'organization_id' => $terminal->organization_id, 'employee_id' => $employee->id, 'modality' => 'FINGERPRINT', 'finger_position' => 'RIGHT_INDEX', 'template_vendor' => 'FAKE', 'template_format' => 'SIMULATOR-V1', 'algorithm_version' => 'SIMULATOR-V1', 'template_data' => $bytes, 'template_checksum' => hash('sha256', $bytes), 'quality_score' => 95, 'version_number' => $version, 'status' => 'ACTIVE', 'source_terminal_id' => $terminal->id, 'enrolled_by_user_id' => $actor->id, 'enrolled_at' => now()];
    }
}
