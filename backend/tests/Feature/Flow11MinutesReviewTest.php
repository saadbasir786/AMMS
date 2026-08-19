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

class Flow11MinutesReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_unit_acknowledgement_makes_flow01_minutes_ready_and_finalize_completes_meeting(): void
    {
        [$meeting, $coordinator, $director, $unit] = $this->submittedMinutesFixture();
        $this->actingAs($director, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.$unit->id.'/acknowledge')->assertOk()->assertJsonPath('data.status', 'READY_TO_FINALIZE');
        $this->actingAs($coordinator, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/finalize')->assertOk()->assertJsonPath('data.status', 'FINALIZED');
        $this->assertSame('COMPLETED', $meeting->fresh()->status);
        $this->actingAs($coordinator, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/finalize')->assertStatus(422)->assertJsonPath('code', 'MINUTES_ALREADY_FINALIZED');
    }

    public function test_final_pdf_is_generated_from_finalized_version_without_biometric_fields(): void
    {
        [$meeting, $coordinator, $director, $unit] = $this->submittedMinutesFixture();
        $this->actingAs($director, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.$unit->id.'/acknowledge')->assertOk();
        $this->actingAs($coordinator, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/finalize')->assertOk();
        $pdf = $this->actingAs($coordinator, 'sanctum')->get('/api/meetings/'.$meeting->id.'/minutes/final.pdf');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertNotEmpty($pdf->headers->get('X-AMMS-Content-SHA256'));
        $this->assertStringNotContainsString('template_data', $pdf->getContent());
        $this->assertStringNotContainsString('match_score', $pdf->getContent());
        $this->assertSame(1, $meeting->fresh()->minutes()->firstOrFail()->document()->count());
    }

    public function test_correction_requires_reason_and_new_version_starts_with_fresh_review(): void
    {
        [$meeting, $coordinator, $director, $unit] = $this->submittedMinutesFixture();
        $this->actingAs($director, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/correction', ['reviewer_type' => 'UNIT', 'organizational_unit_id' => $unit->id])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
        $this->actingAs($director, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/correction', ['reviewer_type' => 'UNIT', 'organizational_unit_id' => $unit->id, 'reason' => 'لطفاً خلاصهٔ بحث اصلاح شود.'])->assertOk()->assertJsonPath('data.status', 'RETURNED_FOR_CORRECTION');
        $this->actingAs($coordinator, 'sanctum')->putJson('/api/meetings/'.$meeting->id.'/minutes/draft', $this->payload('نسخهٔ اصلاح‌شده'))->assertOk()->assertJsonPath('data.current_version.version_number', 2);
        $minutes = $meeting->fresh()->minutes()->with('versions.reviews')->firstOrFail();
        $versions = $minutes->versions->sortBy('version_number')->values();
        $this->assertSame(2, $versions->count());
        $this->assertCount(1, $versions[0]->reviews);
        $this->assertCount(0, $versions[1]->reviews);
        $this->actingAs($coordinator, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/submit')->assertOk();
        $this->assertSame(2, $meeting->fresh()->minutes->current_version_id);
    }

    private function submittedMinutesFixture(): array
    {
        $coordinator = \App\Models\User::query()->where('email', 'tech.coordinator@example.test')->firstOrFail();
        $director = \App\Models\User::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $unit = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
        $participant = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'FLOW11-REV-'.uniqid(), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $coordinator->id, 'subject' => 'جلسهٔ بررسی FLOW-11', 'purpose' => 'REVIEW', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $coordinator->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW11-REV-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $coordinator->id, 'chairperson_employee_id' => $participant->id, 'meeting_date' => now()->addDays(20)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار بررسی', 'status' => 'SCHEDULED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $participant->id, 'organizational_unit_id' => $participant->organizational_unit_id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class);
        $schedule->applyToMeeting($meeting, $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $coordinator->id));
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $coordinator->id);
        $this->actingAs($coordinator, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/start')->assertOk();
        $this->actingAs($coordinator, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/end')->assertOk();
        $this->actingAs($coordinator, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/draft')->assertCreated();
        $this->actingAs($coordinator, 'sanctum')->putJson('/api/meetings/'.$meeting->id.'/minutes/draft', $this->payload('نسخهٔ نخست'))->assertOk();
        $this->actingAs($coordinator, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/submit')->assertOk();
        return [$meeting->fresh(), $coordinator, $director, $unit];
    }

    private function payload(string $summary): array
    {
        return ['summary' => $summary, 'items' => [['agenda_title' => 'بحث اصلی', 'discussion_summary' => 'بحث ثبت شد.']], 'decisions' => [['decision_text' => 'تصمیم ثبت شد.']]];
    }
}
