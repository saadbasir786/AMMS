<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\MeetingVenue;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ScheduleConflictGuard;
use App\Support\ScheduleVenueService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ScheduleConflictFinishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_deactivated_venue_preserves_exact_confirmed_reservation_snapshot_but_cannot_be_reused_for_a_new_slot(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $director = Employee::where('email', 'tech.director@example.test')->firstOrFail();
        $date = now()->addDays(30)->format('Y-m-d');
        $venue = MeetingVenue::create([
            'organization_id' => $tech->organization_id,
            'name' => 'تالار محفوظ',
            'description' => 'نسخه اصلی تالار',
            'location_details' => 'طبقه اول',
            'capacity' => 20,
            'status' => 'ACTIVE',
        ]);
        $snapshot = [
            'type' => ScheduleVenueService::INTERNAL,
            'venue_id' => $venue->id,
            'name' => 'تالار محفوظ',
            'description' => 'نسخه اصلی تالار',
            'location_details' => 'طبقه اول',
            'capacity' => 20,
        ];
        $request = MeetingRequest::create([
            'request_number' => 'VENUE-SNAPSHOT-'.uniqid(),
            'flow_type' => 'INTERNAL_DIRECTORATE',
            'requesting_unit_id' => $tech->id,
            'created_by' => $exec->id,
            'coordinator_id' => $exec->id,
            'subject' => 'رزرو تالار محفوظ',
            'purpose' => 'TEST',
            'status' => 'SCHEDULE_CONFIRMED',
            'scheduled_date' => $date,
            'scheduled_start_time' => '09:00',
            'scheduled_duration_minutes' => 60,
            'scheduled_location' => 'تالار محفوظ — طبقه اول',
            'scheduled_venue_type' => ScheduleVenueService::INTERNAL,
            'scheduled_venue_id' => $venue->id,
            'scheduled_venue_snapshot' => $snapshot,
            'scheduled_chairperson_employee_id' => $director->id,
        ]);

        $venue->update(['name' => 'تالار تغییرنام‌یافته', 'status' => 'INACTIVE']);
        $request = $request->fresh(['requestingUnit', 'authorityContext']);

        $stored = app(ScheduleVersionService::class)->normalize([
            'meeting_date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => $request->scheduled_location,
            'venue_type' => ScheduleVenueService::INTERNAL,
            'venue_id' => $venue->id,
        ], $request);

        $this->assertSame('تالار محفوظ — طبقه اول', $stored['location']);
        $this->assertSame('تالار محفوظ', $stored['venue_snapshot']['name']);
        $this->assertSame(20, $stored['venue_snapshot']['capacity']);

        try {
            app(ScheduleVersionService::class)->normalize([
                'meeting_date' => $date,
                'start_time' => '11:00',
                'end_time' => '12:00',
                'location' => 'تالار تغییرنام‌یافته',
                'venue_type' => ScheduleVenueService::INTERNAL,
                'venue_id' => $venue->id,
            ], $request);
            $this->fail('An inactive venue must not be usable for a new schedule slot.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
            $this->assertSame('INTERNAL_VENUE_UNAVAILABLE', $exception->getMessage());
        }
    }

    public function test_shared_reschedule_resume_guard_rejects_confirmed_request_venue_and_person_reservations(): void
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $director = Employee::where('email', 'tech.director@example.test')->firstOrFail();
        $otherEmployee = Employee::where('email', 'employee.a@example.test')->firstOrFail();
        $date = now()->addDays(31)->format('Y-m-d');
        $venue = MeetingVenue::create([
            'organization_id' => $tech->organization_id,
            'name' => 'تالار رزروشده',
            'status' => 'ACTIVE',
        ]);

        $reservation = MeetingRequest::create([
            'request_number' => 'GUARD-RESERVATION-'.uniqid(),
            'flow_type' => 'INTERNAL_DIRECTORATE',
            'requesting_unit_id' => $tech->id,
            'created_by' => $exec->id,
            'coordinator_id' => $exec->id,
            'subject' => 'رزرو رسمی',
            'purpose' => 'TEST',
            'status' => 'SCHEDULE_CONFIRMED',
            'scheduled_date' => $date,
            'scheduled_start_time' => '10:00',
            'scheduled_duration_minutes' => 60,
            'scheduled_location' => $venue->name,
            'scheduled_venue_type' => ScheduleVenueService::INTERNAL,
            'scheduled_venue_id' => $venue->id,
            'scheduled_venue_snapshot' => ['type' => ScheduleVenueService::INTERNAL, 'venue_id' => $venue->id, 'name' => $venue->name],
            'scheduled_chairperson_employee_id' => $director->id,
        ]);
        $reservation->participants()->create([
            'employee_id' => $director->id,
            'participant_type' => 'FINAL',
            'created_by' => $exec->id,
        ]);

        $request = MeetingRequest::create([
            'request_number' => 'GUARD-CANDIDATE-'.uniqid(),
            'flow_type' => 'INTERNAL_DIRECTORATE',
            'requesting_unit_id' => $tech->id,
            'created_by' => $exec->id,
            'subject' => 'جلسه قابل تغییر',
            'purpose' => 'TEST',
            'status' => 'CONVERTED_TO_MEETING',
        ]);
        $meeting = Meeting::create([
            'meeting_request_id' => $request->id,
            'meeting_number' => 'GUARD-MEETING-'.uniqid(),
            'title' => 'جلسه قابل تغییر',
            'organizing_unit_id' => $tech->id,
            'coordinator_id' => $exec->id,
            'chairperson_employee_id' => $otherEmployee->id,
            'meeting_date' => now()->addDays(10)->format('Y-m-d'),
            'start_time' => '08:00',
            'end_time' => '09:00',
            'location' => 'محل فعلی',
            'venue_type' => ScheduleVenueService::EXTERNAL,
            'venue_snapshot' => ['type' => ScheduleVenueService::EXTERNAL, 'location' => 'محل فعلی'],
            'status' => 'SCHEDULED',
        ]);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING']);
        app(ScheduleVersionService::class)->ensureInitialVersion($meeting, $exec->id);
        $meeting = $meeting->fresh(['participants', 'units.unit', 'organizingUnit', 'authorityContext']);

        $guard = app(ScheduleConflictGuard::class);
        try {
            $guard->assertNoConflict($meeting, [
                'meeting_date' => $date,
                'start_time' => '10:00',
                'end_time' => '11:00',
                'location' => $venue->name,
                'venue_type' => ScheduleVenueService::INTERNAL,
                'venue_id' => $venue->id,
            ]);
            $this->fail('The shared guard must block a venue reserved by a confirmed request.');
        } catch (HttpException $exception) {
            $this->assertSame('INTERNAL_VENUE_SCHEDULE_CONFLICT', $exception->getMessage());
        }

        MeetingParticipant::create([
            'meeting_id' => $meeting->id,
            'employee_id' => $director->id,
            'organizational_unit_id' => $tech->id,
            'role' => 'PARTICIPATING',
        ]);
        $meeting = $meeting->fresh(['participants', 'units.unit', 'organizingUnit', 'authorityContext']);

        try {
            $guard->assertNoConflict($meeting, [
                'meeting_date' => $date,
                'start_time' => '10:00',
                'end_time' => '11:00',
                'location' => 'محل بیرونی',
                'venue_type' => ScheduleVenueService::EXTERNAL,
                'venue_id' => null,
            ]);
            $this->fail('The shared guard must block a person reserved by a confirmed request.');
        } catch (HttpException $exception) {
            $this->assertSame('CHAIRPERSON_SCHEDULE_CONFLICT', $exception->getMessage());
        }
    }
}
