<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HighAuthorityMinutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_high_authority_holder_approves_minutes_and_secretary_finalizes(): void
    {
        [$meeting, $secretary, $director, $authority, $office, $unit] = $this->fixture();
        $this->actingAs($secretary, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/start')->assertOk();
        $this->actingAs($secretary, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/end')->assertOk();
        $this->actingAs($secretary, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/draft')->assertCreated();
        $this->actingAs($secretary, 'sanctum')->putJson('/api/meetings/'.$meeting->id.'/minutes/draft', ['summary' => 'خلاصهٔ رسمی مقام عالی.', 'general_notes' => 'یادداشت رسمی.', 'items' => [['agenda_title' => 'اجندا', 'discussion_summary' => 'بحث تکمیل شد.']], 'decisions' => [['decision_text' => 'تصمیم مقام عالی ثبت شد.']]])->assertOk();
        $this->actingAs($secretary, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/submit')->assertOk();
        $this->actingAs($office, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/authority-approve')->assertStatus(403);
        $this->actingAs($director, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.$unit->id.'/acknowledge')->assertOk()->assertJsonPath('data.status', 'UNDER_REVIEW');
        $this->actingAs($authority, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/authority-approve')->assertOk()->assertJsonPath('data.status', 'READY_TO_FINALIZE');
        $this->actingAs($secretary, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/finalize')->assertOk()->assertJsonPath('data.status', 'FINALIZED');
        $this->assertSame('COMPLETED', $meeting->fresh()->status);
    }

    private function fixture(): array
    {
        $secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
        $director = User::where('email', 'tech.director@example.test')->firstOrFail();
        $authority = User::where('email', 'high.authority@example.test')->firstOrFail();
        $office = User::where('email', 'authority.office@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $officeUnit = OrganizationalUnit::where('code', 'AUTH-OFFICE')->firstOrFail();
        $employee = Employee::where('email', 'tech.director@example.test')->firstOrFail();
        $authorityEmployee = Employee::where('employee_number', 'EMP-AUTH-001')->firstOrFail();
        $organizationAuthority = OrganizationAuthority::firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'FLOW11-HA-'.uniqid(), 'flow_type' => 'DIRECTORATE_WITH_HIGH_AUTHORITY', 'requesting_unit_id' => $tech->id, 'created_by' => $secretary->id, 'subject' => 'جلسهٔ مقام عالی FLOW-11', 'purpose' => 'DECISION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $secretary->id]);
        MeetingRequest::query()->whereKey($request->id)->update(['requesting_unit_id' => $tech->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW11-HA-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $tech->id, 'coordinator_id' => $secretary->id, 'chairperson_employee_id' => $authorityEmployee->id, 'meeting_date' => now()->addDays(20)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار مقام عالی', 'status' => 'SCHEDULED']);
        MeetingAuthorityContext::create(['meeting_id' => $meeting->id, 'organization_authority_id' => $organizationAuthority->id, 'authority_type' => $organizationAuthority->authority_type, 'authority_position_id' => $organizationAuthority->authority_position_id, 'authority_employee_id' => $authorityEmployee->id, 'authority_office_unit_id' => $officeUnit->id]);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $tech->id, 'role' => 'PARTICIPATING']);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
        $schedule = app(ScheduleVersionService::class);
        $version = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => $meeting->location], $secretary->id);
        $schedule->applyToMeeting($meeting, $version);
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $secretary->id);
        return [$meeting->fresh(), $secretary, $director, $authority, $office, $tech];
    }
}
