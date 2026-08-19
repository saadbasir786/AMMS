<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Meetings\StartMeetingExecution;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Support\ParticipantVersionService;
use App\Support\ParticipantChangeValidator;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Flow11ExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authorized_start_captures_immutable_baseline_and_duplicate_start_is_rejected(): void
    {
        [$meeting, $actor] = $this->meetingFixture();
        $response = $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/start', ['start_notes' => 'شروع رسمی جلسه']);
        $response->assertOk()->assertJsonPath('data.status', 'IN_PROGRESS');
        $session = $meeting->fresh()->executionSession;
        $this->assertNotNull($session);
        $this->assertSame('IN_PROGRESS', $meeting->fresh()->status);
        $this->assertSame((int) $meeting->current_schedule_version_id, (int) $session->schedule_version_id);
        $this->assertSame((int) $meeting->current_participant_version_id, (int) $session->participant_version_id);
        $this->assertNotNull($session->started_at);

        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/start')->assertStatus(422)->assertJsonPath('code', 'MEETING_EXECUTION_ALREADY_STARTED');
        $this->assertSame(1, $meeting->fresh()->executionSession()->count());
    }

    public function test_unauthorized_system_admin_cannot_start_and_end_transitions_to_held(): void
    {
        [$meeting, $actor] = $this->meetingFixture();
        $admin = \App\Models\User::query()->where('email', 'system.admin@example.test')->firstOrFail();
        $this->actingAs($admin, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/start')->assertForbidden();

        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/start')->assertOk();
        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/end', ['end_notes' => 'جلسه پایان یافت'])->assertOk()->assertJsonPath('data.status', 'ENDED');
        $this->assertSame('HELD', $meeting->fresh()->status);
        $this->assertSame('ENDED', $meeting->fresh()->executionSession->status);
        $this->assertNotNull($meeting->fresh()->executionSession->ended_at);
    }

    public function test_attendance_is_closed_after_meeting_end_and_participant_changes_are_frozen(): void
    {
        [$meeting, $actor, $participant] = $this->meetingFixture();
        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/start')->assertOk();
        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/end')->assertOk();

        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/attendance/manual', ['employee_id' => $participant->id, 'reason' => 'بعد از ختم جلسه'])->assertStatus(422)->assertJsonPath('code', 'MEETING_ATTENDANCE_CLOSED');
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('MEETING_ALREADY_STARTED');
        app(ParticipantChangeValidator::class)->assertMeetingEditable($meeting->fresh());
    }

    private function meetingFixture(): array
    {
        $actor = \App\Models\User::query()->where('email', 'tech.coordinator@example.test')->firstOrFail();
        $unit = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $participant = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $request = MeetingRequest::create([
            'request_number' => 'FLOW11-'.uniqid(), 'flow_type' => 'MULTI_DIRECTORATE',
            'requesting_unit_id' => $unit->id, 'created_by' => $actor->id,
            'subject' => 'جلسهٔ اجرایی FLOW-11', 'purpose' => 'EXECUTION',
            'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $actor->id,
        ]);
        $meeting = Meeting::create([
            'meeting_request_id' => $request->id, 'meeting_number' => 'FLOW11-M-'.uniqid(),
            'title' => $request->subject, 'organizing_unit_id' => $unit->id,
            'coordinator_id' => $actor->id, 'chairperson_employee_id' => $participant->id,
            'meeting_date' => now()->addDays(20)->format('Y-m-d'), 'start_time' => '10:00',
            'end_time' => '11:00', 'location' => 'تالار اجرایی', 'status' => 'SCHEDULED',
        ]);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $participant->id, 'organizational_unit_id' => $participant->organizational_unit_id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class);
        $schedule->applyToMeeting($meeting, $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $actor->id));
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
        return [$meeting->fresh(), $actor, $participant];
    }
}
