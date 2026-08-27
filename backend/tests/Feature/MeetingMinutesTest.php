<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingMinutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_minutes_draft_is_available_during_execution_but_submission_requires_held(): void
    {
        [$meeting, $actor, $participant] = $this->meetingFixture();
        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/start')->assertOk();
        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/draft')->assertCreated()->assertJsonPath('data.status', 'DRAFT');
        $payload = $this->payload();
        $this->actingAs($actor, 'sanctum')->putJson('/api/meetings/'.$meeting->id.'/minutes/draft', $payload)->assertOk()->assertJsonPath('data.current_version.version_number', 1);
        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/submit')->assertStatus(422)->assertJsonPath('code', 'MINUTES_MEETING_NOT_HELD');
        $this->assertSame('IN_PROGRESS', $meeting->fresh()->status);
    }

    public function test_executive_management_receives_the_minutes_workspace_capabilities(): void
    {
        [$meeting, $actor] = $this->meetingFixture();

        $this->actingAs($actor, 'sanctum')
            ->getJson('/api/meetings/'.$meeting->id.'/action-capabilities')
            ->assertOk()
            ->assertJsonPath('data.minutes.view', true)
            ->assertJsonPath('data.minutes.prepare', true)
            ->assertJsonPath('data.minutes.submit', true)
            ->assertJsonPath('data.minutes.finalize', true)
            ->assertJsonPath('data.minutes.document', false)
            ->assertJsonPath('data.minutes.review', false)
            ->assertJsonPath('data.minutes.approve_authority', false);
    }

    public function test_decision_responsibility_is_saved_and_employee_must_belong_to_selected_unit(): void
    {
        [$meeting, $actor] = $this->meetingFixture();
        $tech = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $techDirector = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $financeDirector = Employee::query()->where('email', 'finance.director@example.test')->firstOrFail();

        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/start')->assertOk();
        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/draft')->assertCreated();

        $payload = $this->payload();
        $payload['decisions'][0]['responsible_unit_id'] = $tech->id;
        $payload['decisions'][0]['responsible_employee_id'] = $techDirector->id;
        $this->actingAs($actor, 'sanctum')
            ->putJson('/api/meetings/'.$meeting->id.'/minutes/draft', $payload)
            ->assertOk()
            ->assertJsonPath('data.current_version.decisions.0.responsible_unit_id', $tech->id)
            ->assertJsonPath('data.current_version.decisions.0.responsible_employee_id', $techDirector->id);

        $payload['decisions'][0]['responsible_employee_id'] = $financeDirector->id;
        $this->actingAs($actor, 'sanctum')
            ->putJson('/api/meetings/'.$meeting->id.'/minutes/draft', $payload)
            ->assertStatus(422)
            ->assertJsonPath('code', 'MINUTES_DECISION_EMPLOYEE_OUTSIDE_UNIT');
    }

    public function test_held_minutes_submission_creates_immutable_version_and_review_rows(): void
    {
        [$meeting, $actor] = $this->meetingFixture();
        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/start')->assertOk();
        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/end')->assertOk();
        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/draft')->assertCreated();
        $this->actingAs($actor, 'sanctum')->putJson('/api/meetings/'.$meeting->id.'/minutes/draft', $this->payload())->assertOk();
        $this->actingAs($actor, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/submit')->assertOk()->assertJsonPath('data.status', 'UNDER_REVIEW');
        $minutes = $meeting->fresh()->minutes()->with(['currentVersion.items', 'currentVersion.decisions', 'currentVersion.attendanceSnapshots', 'currentVersion.reviews'])->firstOrFail();
        $this->assertSame('UNDER_REVIEW', $minutes->status);
        $this->assertSame(1, $minutes->versions()->count());
        $this->assertNotNull($minutes->currentVersion->submitted_at);
        $this->assertCount(1, $minutes->currentVersion->items);
        $this->assertCount(1, $minutes->currentVersion->decisions);
        $this->assertCount(1, $minutes->currentVersion->reviews);
        $this->actingAs($actor, 'sanctum')->putJson('/api/meetings/'.$meeting->id.'/minutes/draft', $this->payload())->assertStatus(422)->assertJsonPath('code', 'MINUTES_VERSION_STALE');
    }

    private function payload(): array
    {
        return [
            'summary' => 'جلسه با موفقیت برگزار و جمع‌بندی شد.',
            'general_notes' => 'یادداشت عمومی جلسه.',
            'items' => [['agenda_title' => 'بررسی برنامه', 'discussion_summary' => 'برنامه بررسی و تأیید شد.']],
            'decisions' => [['decision_text' => 'برنامه برای اجرا ابلاغ گردد.', 'target_date' => now()->addDays(10)->format('Y-m-d')]],
        ];
    }

    private function meetingFixture(): array
    {
        $actor = \App\Models\User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
        $unit = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $participant = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'FLOW11-MIN-'.uniqid(), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $actor->id, 'subject' => 'جلسهٔ صورت‌جلسه FLOW-11', 'purpose' => 'MINUTES', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $actor->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW11-MIN-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $actor->id, 'chairperson_employee_id' => $participant->id, 'meeting_date' => now()->addDays(20)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار صورت‌جلسه', 'status' => 'SCHEDULED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $participant->id, 'organizational_unit_id' => $participant->organizational_unit_id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class);
        $schedule->applyToMeeting($meeting, $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $actor->id));
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
        return [$meeting->fresh(), $actor, $participant];
    }
}
