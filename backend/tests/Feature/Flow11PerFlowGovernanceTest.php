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

class Flow11PerFlowGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_flow01_requires_and_accepts_one_unit_acknowledgement(): void
    {
        [$meeting, $coordinator, $directors] = $this->prepare('MULTI_DIRECTORATE', ['TECH' => 'PARTICIPATING']);
        $minutes = $this->submit($meeting, $coordinator);
        $this->actingAs($directors['TECH'], 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.OrganizationalUnit::where('code', 'TECH')->value('id').'/acknowledge')->assertOk();
        $this->assertSame(1, $minutes->fresh()->currentVersion->reviews()->where('status', 'ACKNOWLEDGED')->count());
    }

    public function test_flow02_requires_exactly_two_directorate_acknowledgements(): void
    {
        [$meeting, $coordinator, $directors] = $this->prepare('MULTI_DIRECTORATE', ['TECH' => 'PARTICIPATING', 'FIN' => 'PARTICIPATING']);
        $minutes = $this->submit($meeting, $coordinator);
        foreach (['TECH', 'FIN'] as $code) {
            $unitId = OrganizationalUnit::where('code', $code)->value('id');
            $this->actingAs($directors[$code], 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.$unitId.'/acknowledge')->assertOk();
        }
        $this->assertSame(2, $minutes->fresh()->currentVersion->reviews()->where('reviewer_type', 'UNIT')->where('status', 'ACKNOWLEDGED')->count());
        $this->assertSame('READY_TO_FINALIZE', $minutes->fresh()->status);
    }

    public function test_flow03_rejects_cross_unit_ack_and_resets_acknowledgements_on_correction(): void
    {
        [$meeting, $coordinator, $directors] = $this->prepare('MULTI_DIRECTORATE', ['TECH' => 'PARTICIPATING', 'FIN' => 'PARTICIPATING', 'HR' => 'PARTICIPATING']);
        $minutes = $this->submit($meeting, $coordinator);
        $techId = OrganizationalUnit::where('code', 'TECH')->value('id');
        $financeId = OrganizationalUnit::where('code', 'FIN')->value('id');
        $hrId = OrganizationalUnit::where('code', 'HR')->value('id');
        $this->actingAs($directors['TECH'], 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.$techId.'/acknowledge')->assertOk();
        $this->actingAs($directors['FIN'], 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.$financeId.'/acknowledge')->assertOk();
        $this->actingAs($directors['FIN'], 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.$hrId.'/acknowledge')->assertStatus(403);
        $versionOne = $minutes->fresh()->currentVersion;
        $this->actingAs($directors['TECH'], 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/correction', ['reviewer_type' => 'UNIT', 'organizational_unit_id' => $techId, 'reason' => 'اصلاح خلاصهٔ بحث'])->assertOk();
        $this->actingAs($coordinator, 'sanctum')->putJson('/api/meetings/'.$meeting->id.'/minutes/draft', ['summary' => 'نسخهٔ دوم خلاصه.', 'general_notes' => null, 'items' => [['agenda_title' => 'اجندا', 'discussion_summary' => 'بحث اصلاح شد.']], 'decisions' => [['decision_text' => 'تصمیم نسخهٔ دوم.']]])->assertOk();
        $this->actingAs($coordinator, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/submit')->assertOk();
        $versionTwo = $minutes->fresh()->currentVersion;
        $this->assertNotSame($versionOne->id, $versionTwo->id);
        $this->assertSame(0, $versionTwo->reviews()->where('status', 'ACKNOWLEDGED')->count());
        foreach (['TECH', 'FIN', 'HR'] as $code) {
            $unitId = OrganizationalUnit::where('code', $code)->value('id');
            $this->actingAs($directors[$code], 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.$unitId.'/acknowledge')->assertOk();
        }
        $this->assertSame(2, $versionOne->fresh()->reviews()->whereIn('status', ['ACKNOWLEDGED', 'CORRECTION_REQUESTED'])->count());
        $this->assertSame(3, $versionTwo->fresh()->reviews()->where('status', 'ACKNOWLEDGED')->count());
    }

    public function test_flow04_requires_requester_ack_and_exact_high_authority_approval(): void
    {
        [$meeting, $secretary, $directors, $authority] = $this->prepare('DIRECTORATE_WITH_HIGH_AUTHORITY', ['TECH' => 'REQUESTER'], true);
        $minutes = $this->submit($meeting, $secretary);
        $techId = OrganizationalUnit::where('code', 'TECH')->value('id');
        $this->actingAs($directors['TECH'], 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.$techId.'/acknowledge')->assertOk();
        $this->actingAs($authority, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/authority-approve')->assertOk();
        $this->assertSame('READY_TO_FINALIZE', $minutes->fresh()->status);
    }

    public function test_flow05_requires_all_formal_directorates_authority_and_rejects_authority_office(): void
    {
        [$meeting, $secretary, $directors, $authority, $office] = $this->prepare('DIRECTORATE_WITH_HIGH_AUTHORITY', ['TECH' => 'PARTICIPATING', 'FIN' => 'PARTICIPATING', 'HR' => 'PARTICIPATING'], true);
        $minutes = $this->submit($meeting, $secretary);
        foreach (['TECH', 'FIN', 'HR'] as $code) {
            $unitId = OrganizationalUnit::where('code', $code)->value('id');
            $this->actingAs($directors[$code], 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.$unitId.'/acknowledge')->assertOk();
        }
        $this->actingAs($office, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/authority-approve')->assertStatus(403);
        $this->actingAs($authority, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/authority-approve')->assertOk();
        $this->assertSame('READY_TO_FINALIZE', $minutes->fresh()->status);
    }

    public function test_flow06_has_no_requester_and_requires_all_participating_directorates_plus_authority(): void
    {
        [$meeting, $secretary, $directors, $authority] = $this->prepare('HIGH_AUTHORITY_INITIATED', ['TECH' => 'PARTICIPATING', 'FIN' => 'PARTICIPATING', 'HR' => 'PARTICIPATING'], true, true);
        $this->assertNull($meeting->fresh()->request->requesting_unit_id);
        $minutes = $this->submit($meeting, $secretary);
        foreach (['TECH', 'FIN', 'HR'] as $code) {
            $unitId = OrganizationalUnit::where('code', $code)->value('id');
            $this->actingAs($directors[$code], 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/reviews/'.$unitId.'/acknowledge')->assertOk();
        }
        $this->actingAs($authority, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/authority-approve')->assertOk();
        $this->assertSame('READY_TO_FINALIZE', $minutes->fresh()->status);
    }

    private function submit(Meeting $meeting, User $preparer)
    {
        $this->actingAs($preparer, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/start')->assertOk();
        $this->actingAs($preparer, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/execution/end')->assertOk();
        $this->actingAs($preparer, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/draft')->assertCreated();
        $this->actingAs($preparer, 'sanctum')->putJson('/api/meetings/'.$meeting->id.'/minutes/draft', ['summary' => 'خلاصهٔ آزمون per-flow.', 'general_notes' => 'یادداشت آزمون.', 'items' => [['agenda_title' => 'اجندا', 'discussion_summary' => 'بحث ثبت شد.']], 'decisions' => [['decision_text' => 'تصمیم ثبت شد.']]])->assertOk();
        $this->actingAs($preparer, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/minutes/submit')->assertOk();
        return $meeting->minutes()->firstOrFail();
    }

    private function prepare(string $flowType, array $unitRoles, bool $authorityControlled = false, bool $noRequester = false): array
    {
        $coordinator = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
        $authority = User::where('email', 'high.authority@example.test')->firstOrFail();
        $office = User::where('email', 'authority.office@example.test')->firstOrFail();
        $techUnit = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $officeUnit = OrganizationalUnit::where('code', 'AUTH-OFFICE')->firstOrFail();
        $authorityEmployee = Employee::where('employee_number', 'EMP-AUTH-001')->firstOrFail();
        $organizationAuthority = OrganizationAuthority::firstOrFail();
        $creator = $authorityControlled ? $secretary : $coordinator;
        $request = MeetingRequest::create(['request_number' => 'FLOW11-PER-FLOW-'.uniqid(), 'flow_type' => $flowType, 'requesting_unit_id' => $noRequester ? null : $techUnit->id, 'created_by' => $creator->id, 'subject' => 'جلسهٔ آزمون مستقل '.$flowType, 'purpose' => 'GOVERNANCE', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $creator->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW11-PER-FLOW-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $authorityControlled ? $officeUnit->id : $techUnit->id, 'coordinator_id' => $creator->id, 'chairperson_employee_id' => $authorityControlled ? $authorityEmployee->id : Employee::where('email', 'tech.director@example.test')->value('id'), 'meeting_date' => now()->addDays(30)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار آزمون per-flow', 'status' => 'SCHEDULED']);
        if ($authorityControlled) MeetingAuthorityContext::create(['meeting_id' => $meeting->id, 'organization_authority_id' => $organizationAuthority->id, 'authority_type' => $organizationAuthority->authority_type, 'authority_position_id' => $organizationAuthority->authority_position_id, 'authority_employee_id' => $authorityEmployee->id, 'authority_office_unit_id' => $officeUnit->id]);
        $directors = [];
        foreach ($unitRoles as $code => $role) {
            $unit = OrganizationalUnit::where('code', $code)->firstOrFail();
            MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => $role]);
            $email = ['TECH' => 'tech.director@example.test', 'FIN' => 'finance.director@example.test', 'HR' => 'hr.director@example.test'][$code];
            $directors[$code] = User::where('email', $email)->firstOrFail();
            $employee = Employee::where('email', $email)->firstOrFail();
            MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
        }
        $schedule = app(ScheduleVersionService::class);
        $version = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => $meeting->location], $creator->id);
        $schedule->applyToMeeting($meeting, $version);
        app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $creator->id);
        return [$meeting->fresh(), $authorityControlled ? $secretary : $coordinator, $directors, $authority, $office];
    }
}
