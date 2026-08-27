<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricAttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_manual_attendance_requires_reason_and_records_immutable_override(): void
    {
        [$meeting, $participant, $actor] = $this->meetingFixture();
        $base = $this->actingAs($actor, 'sanctum');
        $base->postJson('/api/meetings/'.$meeting->id.'/attendance/manual', ['employee_id' => $participant->id])
            ->assertStatus(422)->assertJsonPath('code', 'MANUAL_ATTENDANCE_REASON_REQUIRED');

        $base->postJson('/api/meetings/'.$meeting->id.'/attendance/manual', ['employee_id' => $participant->id, 'reason' => 'اثر انگشت ثبت نشده است'])
            ->assertCreated()->assertJsonPath('data.attendance_method', 'MANUAL_OVERRIDE');

        $this->assertDatabaseHas('meeting_attendance_records', ['meeting_id' => $meeting->id, 'employee_id' => $participant->id, 'attendance_method' => 'MANUAL_OVERRIDE']);
        $base->postJson('/api/meetings/'.$meeting->id.'/attendance/manual', ['employee_id' => $participant->id, 'reason' => 'تلاش مجدد'])
            ->assertStatus(409)->assertJsonPath('code', 'ATTENDANCE_ALREADY_RECORDED');
    }

    public function test_manual_attendance_rejects_nonparticipant_and_unauthorized_actor(): void
    {
        [$meeting, $participant] = $this->meetingFixture();
        $nonparticipant = User::query()->where('email', 'finance.director@example.test')->firstOrFail()->employee;
        $this->actingAs(User::query()->where('email', 'tech.exec@example.test')->firstOrFail(), 'sanctum')
            ->postJson('/api/meetings/'.$meeting->id.'/attendance/manual', ['employee_id' => $nonparticipant->id, 'reason' => 'اثر انگشت خوانده نشد'])
            ->assertStatus(422)->assertJsonPath('code', 'ATTENDANCE_EMPLOYEE_NOT_PARTICIPANT');

        $this->actingAs(User::query()->where('email', 'system.admin@example.test')->firstOrFail(), 'sanctum')
            ->postJson('/api/meetings/'.$meeting->id.'/attendance/manual', ['employee_id' => $participant->id, 'reason' => 'دلیل آزمایشی'])
            ->assertForbidden();
    }

    private function meetingFixture(): array
    {
        $actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $unit = \App\Models\OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $participant = \App\Models\Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'FLOW10A-'.uniqid(), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $actor->id, 'subject' => 'جلسهٔ حضور FLOW-10A', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $actor->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW10A-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $actor->id, 'chairperson_employee_id' => $participant->id, 'meeting_date' => now()->addDays(20)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار آزمایش', 'status' => 'SCHEDULED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $participant->id, 'organizational_unit_id' => $participant->organizational_unit_id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class);
        $schedule->applyToMeeting($meeting, $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $actor->id));
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
        return [$meeting->fresh(), $participant, $actor];
    }
}
