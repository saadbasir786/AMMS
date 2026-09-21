<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\MeetingVenue;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\CentralScheduleConflictService;
use App\Support\ScheduleVenueService;
use App\Support\ScheduleVersionService;
use App\Services\MeetingRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CentralizedScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_real_overlap_blocks_and_exact_boundary_is_allowed(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $techDirector = Employee::where('email', 'tech.director@example.test')->firstOrFail();
        $financeDirector = Employee::where('email', 'finance.director@example.test')->firstOrFail();
        $date = now()->addDays(20)->format('Y-m-d');
        $other = $this->meeting($tech, $techDirector, $date, '09:00', '10:00');
        $candidate = $this->meeting($finance, $financeDirector, $date, '10:00', '11:00');
        MeetingParticipant::create(['meeting_id' => $candidate->id, 'employee_id' => $techDirector->id, 'organizational_unit_id' => $tech->id, 'role' => 'PARTICIPATING']);

        $service = app(CentralScheduleConflictService::class);
        $boundary = $service->evaluateMeeting($candidate, $this->schedule($date, '10:00', '11:00', 'EXTERNAL_LOCATION', 'خارجی'), true);
        $this->assertSame([], $boundary['blocking_conflicts']);
        $this->assertDatabaseHas('schedule_conflict_locks', ['schedule_date' => $date]);

        $overlap = $service->evaluateMeeting($candidate, $this->schedule($date, '09:30', '10:30', 'EXTERNAL_LOCATION', 'خارجی'), true);
        $this->assertSame('CHAIRPERSON_SCHEDULE_CONFLICT', $overlap['blocking_conflicts'][0]['type']);
        $this->assertNotNull($other->id);
    }

    public function test_internal_venue_blocks_by_identity_and_directorate_overlap_is_only_a_warning(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $techDirector = Employee::where('email', 'tech.director@example.test')->firstOrFail();
        $techEmployee = Employee::where('email', 'employee.a@example.test')->firstOrFail();
        $venue = MeetingVenue::create(['organization_id' => $tech->organization_id, 'name' => 'تالار داخلی', 'location_details' => 'ساختمان مرکزی', 'capacity' => 20, 'status' => 'ACTIVE']);
        $date = now()->addDays(21)->format('Y-m-d');
        $other = $this->meeting($tech, $techDirector, $date, '09:00', '11:00', $venue);
        $version = app(ScheduleVersionService::class)->ensureInitialVersion($other, $other->coordinator_id);
        $this->assertSame($venue->id, (int) $version->venue_id);
        $this->assertSame('تالار داخلی', $version->venue_snapshot['name']);
        $candidate = $this->meeting($tech, $techEmployee, $date, '10:00', '12:00');

        $service = app(CentralScheduleConflictService::class);
        $warning = $service->evaluateMeeting($candidate, $this->schedule($date, '10:00', '12:00', 'EXTERNAL_LOCATION', 'نشانی متفاوت'), true);
        $this->assertSame([], $warning['blocking_conflicts']);
        $this->assertSame('DIRECTORATE_SCHEDULE_OVERLAP', $warning['warnings'][0]['type']);

        $blocked = $service->evaluateMeeting($candidate, $this->schedule($date, '10:00', '12:00', 'INTERNAL_VENUE', $venue->name, $venue->id), true);
        $this->assertSame('INTERNAL_VENUE_SCHEDULE_CONFLICT', $blocked['blocking_conflicts'][0]['type']);
        $differentVenue = MeetingVenue::create(['organization_id' => $tech->organization_id, 'name' => 'تالار دوم', 'status' => 'ACTIVE']);
        $sameDisplayTextDifferentIdentity = $service->evaluateMeeting($candidate, $this->schedule($date, '10:00', '12:00', 'INTERNAL_VENUE', $venue->name, $differentVenue->id), true);
        $this->assertSame([], $sameDisplayTextDifferentIdentity['blocking_conflicts']);
        $this->assertNotNull($other->id);
    }

    public function test_high_authority_overlap_is_classified_as_a_hard_conflict(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $authority = Employee::where('email', 'high.authority@example.test')->firstOrFail();
        $techDirector = Employee::where('email', 'tech.director@example.test')->firstOrFail();
        $date = now()->addDays(22)->format('Y-m-d');
        $other = $this->meeting($tech, $authority, $date, '09:00', '11:00');
        $authorityRecord = OrganizationAuthority::firstOrFail();
        MeetingAuthorityContext::create(['meeting_id' => $other->id, 'organization_authority_id' => $authorityRecord->id, 'authority_employee_id' => $authority->id, 'authority_type' => 'DIRECTOR_GENERAL', 'authority_position_id' => $authorityRecord->authority_position_id, 'authority_office_unit_id' => $authorityRecord->authority_office_unit_id]);
        $candidate = $this->meeting($tech, $techDirector, $date, '10:00', '12:00');
        MeetingAuthorityContext::create(['meeting_id' => $candidate->id, 'organization_authority_id' => $authorityRecord->id, 'authority_employee_id' => $authority->id, 'authority_type' => 'DIRECTOR_GENERAL', 'authority_position_id' => $authorityRecord->authority_position_id, 'authority_office_unit_id' => $authorityRecord->authority_office_unit_id]);

        $result = app(CentralScheduleConflictService::class)->evaluateMeeting($candidate, $this->schedule($date, '10:00', '12:00', 'EXTERNAL_LOCATION', 'خارجی'), true);
        $this->assertSame('HIGH_AUTHORITY_SCHEDULE_CONFLICT', $result['blocking_conflicts'][0]['type']);
    }

    public function test_initial_internal_schedule_confirmation_revalidates_the_central_rules(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $chairperson = Employee::where('email', 'tech.director@example.test')->firstOrFail();
        $other = $this->meeting($tech, $chairperson, now()->addDays(23)->format('Y-m-d'), '09:00', '11:00');
        $request = MeetingRequest::create(['request_number' => 'INITIAL-CONFLICT-'.uniqid(), 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $tech->id, 'created_by' => $exec->id, 'coordinator_id' => $exec->id, 'subject' => 'جلسه زمان‌بندی اولیه', 'purpose' => 'TEST', 'status' => 'SCHEDULING']);

        $this->expectException(HttpException::class);
        app(MeetingRequestService::class)->confirmSchedule($exec, $request, [
            'meeting_date' => $other->meeting_date->toDateString(), 'start_time' => '10:00', 'duration_minutes' => 60,
            'location' => 'محل خارجی', 'chairperson_employee_id' => $chairperson->id, 'participant_ids' => [],
        ]);
    }

    public function test_confirmed_requests_reserve_people_and_internal_venues_without_blocking_back_to_back_time(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $chairperson = Employee::where('email', 'tech.director@example.test')->firstOrFail();
        $otherEmployee = Employee::where('email', 'employee.a@example.test')->firstOrFail();
        $venue = MeetingVenue::create(['organization_id' => $tech->organization_id, 'name' => 'محل رزرو آزمون', 'status' => 'ACTIVE']);
        $date = now()->addDays(24)->format('Y-m-d');
        $this->confirmedRequest($tech, $chairperson, $date, '09:00', 60, $venue, [$chairperson->id]);
        $second = $this->confirmedRequest($tech, $otherEmployee, $date, '10:00', 60, null, [$otherEmployee->id]);

        $boundary = app(CentralScheduleConflictService::class)->evaluateRequest($second, $this->schedule($date, '10:00', '11:00', 'EXTERNAL_LOCATION', 'محل دیگر'), $otherEmployee->id, [$otherEmployee->id]);
        $this->assertSame([], $boundary['blocking_conflicts']);
        $personConflict = app(CentralScheduleConflictService::class)->evaluateRequest($second, $this->schedule($date, '09:30', '10:30', 'EXTERNAL_LOCATION', 'محل دیگر'), $chairperson->id, [$chairperson->id]);
        $this->assertSame('CHAIRPERSON_SCHEDULE_CONFLICT', $personConflict['blocking_conflicts'][0]['type']);
        $venueConflict = app(CentralScheduleConflictService::class)->evaluateRequest($second, $this->schedule($date, '09:30', '10:30', 'INTERNAL_VENUE', $venue->name, $venue->id), $otherEmployee->id, [$otherEmployee->id]);
        $this->assertSame('INTERNAL_VENUE_SCHEDULE_CONFLICT', $venueConflict['blocking_conflicts'][0]['type']);
    }

    public function test_converted_request_is_not_counted_twice_but_its_official_meeting_remains_authoritative(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $chairperson = Employee::where('email', 'tech.director@example.test')->firstOrFail();
        $date = now()->addDays(25)->format('Y-m-d');
        $request = $this->confirmedRequest($tech, $chairperson, $date, '09:00', 60, null, [$chairperson->id]);
        Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'CONVERTED-'.uniqid(), 'title' => 'جلسه تبدیل‌شده', 'organizing_unit_id' => $tech->id, 'coordinator_id' => $exec->id, 'chairperson_employee_id' => $chairperson->id, 'meeting_date' => $date, 'start_time' => '09:00', 'end_time' => '10:00', 'location' => 'محل خارجی', 'venue_type' => 'EXTERNAL_LOCATION', 'status' => 'SCHEDULED']);
        $candidate = $this->confirmedRequest($tech, Employee::where('email', 'employee.a@example.test')->firstOrFail(), $date, '10:00', 60, null, []);
        $result = app(CentralScheduleConflictService::class)->evaluateRequest($candidate, $this->schedule($date, '09:30', '10:30', 'EXTERNAL_LOCATION', 'محل خارجی'), $chairperson->id, [$chairperson->id]);
        $this->assertCount(1, $result['blocking_conflicts']);
        $this->assertSame('CHAIRPERSON_SCHEDULE_CONFLICT', $result['blocking_conflicts'][0]['type']);
    }

    public function test_internal_venue_admin_can_manage_lifecycle_but_scoped_scheduler_cannot(): void
    {
        $admin = User::where('email', 'system.admin@example.test')->firstOrFail();
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $created = $this->actingAs($admin, 'sanctum')->postJson('/api/admin/meeting-venues', ['organization_id' => $tech->organization_id, 'name' => 'محل قابل مدیریت', 'location_details' => 'ساختمان اداری', 'capacity' => 30])->assertCreated()->json('data');
        $this->assertSame('ACTIVE', $created['status']);
        $this->actingAs($exec, 'sanctum')->postJson('/api/admin/meeting-venues/'.$created['id'].'/deactivate')->assertForbidden();
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/meeting-venues/'.$created['id'], ['name' => 'محل اصلاح‌شده', 'location_details' => 'طبقه دوم', 'capacity' => 40])->assertOk();
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/meeting-venues/'.$created['id'].'/deactivate')->assertOk()->assertJsonPath('data.status', 'INACTIVE');
        $this->assertDatabaseHas('meeting_venues', ['id' => $created['id'], 'status' => 'INACTIVE', 'name' => 'محل اصلاح‌شده']);
        try {
            app(ScheduleVenueService::class)->normalize(['venue_type' => 'INTERNAL_VENUE', 'venue_id' => $created['id']], null);
            $this->fail('Inactive venues must not be accepted for new schedules.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/meeting-venues/'.$created['id'].'/activate')->assertOk()->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_schedule_conflict_preview_requires_scheduling_authority_and_rejects_out_of_scope_people(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $director = User::where('email', 'tech.director@example.test')->firstOrFail();
        $techEmployee = Employee::where('email', 'employee.a@example.test')->firstOrFail();
        $financeEmployee = Employee::where('email', 'finance.director@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'PREVIEW-'.uniqid(), 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $tech->id, 'created_by' => $exec->id, 'subject' => 'پیش‌نمایش تعارض', 'purpose' => 'TEST', 'status' => 'SCHEDULING']);
        $query = ['meeting_date' => now()->addDays(26)->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '10:00', 'chairperson_employee_id' => $financeEmployee->id, 'participant_ids' => []];
        $queryString = http_build_query($query);
        $this->actingAs($director, 'sanctum')->getJson('/api/meeting-requests/'.$request->id.'/schedule-conflicts?'.$queryString)->assertForbidden();
        $this->actingAs($exec, 'sanctum')->getJson('/api/meeting-requests/'.$request->id.'/schedule-conflicts?'.$queryString)->assertForbidden();
        $validQuery = http_build_query([...$query, 'chairperson_employee_id' => $techEmployee->id, 'participant_ids' => [$techEmployee->id], 'location' => 'محل خارجی']);
        $this->actingAs($exec, 'sanctum')->getJson('/api/meeting-requests/'.$request->id.'/schedule-conflicts?'.$validQuery)->assertOk();
    }

    private function meeting(OrganizationalUnit $unit, Employee $chairperson, string $date, string $start, string $end, ?MeetingVenue $venue = null): Meeting
    {
        $user = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'CONFLICT-'.uniqid(), 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $user->id, 'subject' => 'جلسه آزمون تعارض', 'purpose' => 'TEST', 'status' => 'CONVERTED_TO_MEETING']);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'CONFLICT-'.uniqid(), 'title' => 'جلسه آزمون تعارض', 'organizing_unit_id' => $unit->id, 'coordinator_id' => $user->id, 'chairperson_employee_id' => $chairperson->id, 'meeting_date' => $date, 'start_time' => $start, 'end_time' => $end, 'location' => $venue?->name ?: 'محل خارجی', 'venue_type' => $venue ? ScheduleVenueService::INTERNAL : ScheduleVenueService::EXTERNAL, 'venue_id' => $venue?->id, 'venue_snapshot' => $venue ? ['type' => ScheduleVenueService::INTERNAL, 'venue_id' => $venue->id, 'name' => $venue->name] : ['type' => ScheduleVenueService::EXTERNAL, 'location' => 'محل خارجی'], 'status' => 'SCHEDULED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'REQUESTING']);
        return $meeting->fresh(['units.unit', 'organizingUnit', 'participants', 'authorityContext']);
    }

    private function schedule(string $date, string $start, string $end, string $venueType, string $location, ?int $venueId = null): array
    {
        return ['meeting_date' => $date, 'start_time' => $start, 'end_time' => $end, 'venue_type' => $venueType, 'venue_id' => $venueId, 'location' => $location];
    }

    private function confirmedRequest(OrganizationalUnit $unit, Employee $chairperson, string $date, string $start, int $duration, ?MeetingVenue $venue, array $participantIds): MeetingRequest
    {
        $user = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'RESERVED-'.uniqid(), 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $user->id, 'coordinator_id' => $user->id, 'subject' => 'رزرو تأییدشده', 'purpose' => 'TEST', 'status' => 'SCHEDULE_CONFIRMED', 'scheduled_date' => $date, 'scheduled_start_time' => $start, 'scheduled_duration_minutes' => $duration, 'scheduled_location' => $venue?->name ?: 'محل خارجی', 'scheduled_venue_type' => $venue ? 'INTERNAL_VENUE' : 'EXTERNAL_LOCATION', 'scheduled_venue_id' => $venue?->id, 'scheduled_venue_snapshot' => $venue ? ['type' => 'INTERNAL_VENUE', 'venue_id' => $venue->id, 'name' => $venue->name] : ['type' => 'EXTERNAL_LOCATION', 'location' => 'محل خارجی'], 'scheduled_chairperson_employee_id' => $chairperson->id]);
        foreach ($participantIds as $employeeId) $request->participants()->create(['employee_id' => $employeeId, 'participant_type' => 'FINAL', 'created_by' => $user->id]);
        return $request->fresh(['participants', 'units.unit', 'requestingUnit', 'authorityContext']);
    }
}
