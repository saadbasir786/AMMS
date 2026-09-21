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
}
