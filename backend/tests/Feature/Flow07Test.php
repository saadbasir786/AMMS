<?php

namespace Tests\Feature;

use App\Actions\Meetings\AcknowledgeMeetingRescheduleProposal;
use App\Actions\Meetings\BackfillMeetingScheduleVersions;
use App\Actions\Meetings\ConfirmMeetingReschedule;
use App\Actions\Meetings\CreateAuthorityRescheduleProposal;
use App\Actions\Meetings\CreateMeetingRescheduleCase;
use App\Actions\Meetings\RequestMeetingScheduleAlternative;
use App\Actions\Meetings\ResolveMeetingScheduleAlternative;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingUnit;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\User;
use App\Support\ScheduleGovernanceResolver;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class Flow07Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_backfill_is_idempotent_and_preserves_original_schedule(): void
    {
        [$meeting] = $this->officialMeeting();
        $original = [$meeting->meeting_date->toDateString(), $meeting->start_time, $meeting->end_time, $meeting->location];
        $first = (new BackfillMeetingScheduleVersions)();
        $second = (new BackfillMeetingScheduleVersions)();
        $meeting = $meeting->fresh(['currentScheduleVersion', 'scheduleVersions']);
        $this->assertSame([], $second['backfilled_meeting_ids']);
        $this->assertSame(1, $meeting->scheduleVersions->count());
        $this->assertSame(1, $meeting->currentScheduleVersion->version_number);
        $this->assertSame($original[0], $meeting->meeting_date->toDateString());
        $this->assertSame($original[1], $meeting->start_time);
        $this->assertSame($original[2], $meeting->end_time);
        $this->assertSame($original[3], $meeting->location);
    }

    public function test_governance_resolver_maps_all_originating_flow_types(): void
    {
        [$meeting] = $this->officialMeeting();
        $expected = [
            'INTERNAL_DIRECTORATE' => 'INTERNAL_SINGLE_UNIT', 'INTER_DIRECTORATE' => 'PEER_MULTI_UNIT',
            'MULTI_DIRECTORATE' => 'PEER_MULTI_UNIT', 'DIRECTORATE_WITH_HIGH_AUTHORITY' => 'AUTHORITY_CONTROLLED',
            'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' => 'AUTHORITY_CONTROLLED', 'HIGH_AUTHORITY_INITIATED' => 'AUTHORITY_CONTROLLED',
        ];
        foreach ($expected as $flowType => $mode) {
            $meeting->request->update(['flow_type' => $flowType]);
            $result = app(ScheduleGovernanceResolver::class)->resolve($meeting->fresh(['request', 'units', 'authorityContext']));
            $this->assertSame($mode, $result['mode']);
        }
    }

    public function test_authority_reschedule_preserves_request_and_meeting_identity(): void
    {
        [$meeting, $users, $units] = $this->officialMeeting();
        $case = (new CreateMeetingRescheduleCase)($users['secretary'], $meeting, 'تداخل برنامه رسمی');
        $proposal = (new CreateAuthorityRescheduleProposal)($users['secretary'], $meeting, $case, $this->futureSchedule('تالار جدید'));
        foreach (['techDirector', 'financeDirector', 'hrDirector'] as $key) {
            (new AcknowledgeMeetingRescheduleProposal)($users[$key], $meeting, $case, $proposal);
        }
        $confirmed = (new ConfirmMeetingReschedule)($users['secretary'], $meeting, $case);
        $fresh = $confirmed->fresh(['request', 'currentScheduleVersion', 'scheduleVersions', 'units', 'participants', 'authorityContext']);
        $this->assertSame($meeting->id, $fresh->id);
        $this->assertSame($meeting->meeting_number, $fresh->meeting_number);
        $this->assertSame('CONVERTED_TO_MEETING', $fresh->request->status);
        $this->assertSame('SCHEDULED', $fresh->status);
        $this->assertSame(2, $fresh->currentScheduleVersion->version_number);
        $this->assertSame(2, $fresh->scheduleVersions->count());
        $this->assertSame(3, $fresh->units->count());
        $this->assertSame(3, $fresh->participants->count());
        $this->assertSame($meeting->chairperson_employee_id, $fresh->chairperson_employee_id);
        $this->assertSame($meeting->authorityContext->authority_employee_id, $fresh->authorityContext->authority_employee_id);
    }

    public function test_reason_active_case_no_change_and_started_meeting_are_blocked(): void
    {
        [$meeting, $users] = $this->officialMeeting();
        $this->expectException(HttpException::class);
        (new CreateMeetingRescheduleCase)($users['secretary'], $meeting, '   ');
    }

    public function test_alternative_blocks_confirmation_and_revised_proposal_requires_new_acknowledgements(): void
    {
        [$meeting, $users] = $this->officialMeeting();
        $case = (new CreateMeetingRescheduleCase)($users['secretary'], $meeting, 'تداخل برنامه ریاست');
        $proposal = (new CreateAuthorityRescheduleProposal)($users['secretary'], $meeting, $case, $this->futureSchedule('دفتر مقام'));
        foreach (['techDirector', 'financeDirector'] as $key) {
            (new AcknowledgeMeetingRescheduleProposal)($users[$key], $meeting, $case, $proposal);
        }
        $alternative = (new RequestMeetingScheduleAlternative)($users['hrDirector'], $meeting, $case, $proposal, ['reason' => 'برنامه رسمی ریاست مناسب نیست.']);
        try {
            (new ConfirmMeetingReschedule)($users['secretary'], $meeting, $case);
            $this->fail('Confirmation with an open alternative should fail.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $revised = (new CreateAuthorityRescheduleProposal)($users['secretary'], $meeting, $case, $this->futureSchedule('دفتر مقام', '11:00', '12:30'));
        $this->assertSame('SUPERSEDED', $proposal->fresh()->status);
        $this->assertSame(0, $revised->acknowledgements()->count());
        (new ResolveMeetingScheduleAlternative)($users['secretary'], $meeting, $alternative);
        foreach (['techDirector', 'financeDirector', 'hrDirector'] as $key) {
            (new AcknowledgeMeetingRescheduleProposal)($users[$key], $meeting, $case, $revised);
        }
        $confirmed = (new ConfirmMeetingReschedule)($users['secretary'], $meeting, $case);
        $this->assertSame(2, $confirmed->fresh('currentScheduleVersion')->currentScheduleVersion->version_number);
    }

    public function test_direct_schedule_mutation_route_does_not_exist(): void
    {
        [$meeting, $users] = $this->officialMeeting();
        $this->actingAs($users['secretary'], 'sanctum')->patchJson("/api/meetings/{$meeting->id}/schedule", $this->futureSchedule('نباید اعمال شود'))->assertStatus(404);
        $this->assertSame(1, $meeting->fresh()->scheduleVersions()->count());
    }

    public function test_authority_conflict_is_rechecked_before_confirmation(): void
    {
        [$meeting, $users] = $this->officialMeeting();
        [$other] = $this->officialMeeting('FLOW07-OTHER');
        $case = (new CreateMeetingRescheduleCase)($users['secretary'], $meeting, 'تداخل تقویم');
        $this->expectException(HttpException::class);
        (new CreateAuthorityRescheduleProposal)($users['secretary'], $meeting, $case, [
            'meeting_date' => $other->meeting_date->toDateString(), 'start_time' => '09:30', 'end_time' => '10:30', 'location' => 'محل دیگر',
        ]);
    }

    private function futureSchedule(string $location, string $start = '10:00', string $end = '11:00'): array
    {
        return ['meeting_date' => now()->addDays(10)->format('Y-m-d'), 'start_time' => $start, 'end_time' => $end, 'location' => $location];
    }

    private function officialMeeting(string $suffix = 'FLOW07'): array
    {
        $user = fn (string $email) => User::where('email', $email)->firstOrFail();
        $unit = fn (string $code) => OrganizationalUnit::where('code', $code)->firstOrFail();
        $users = [
            'secretary' => $user('executive.secretary@example.test'), 'authority' => $user('high.authority@example.test'), 'techDirector' => $user('tech.director@example.test'),
            'financeDirector' => $user('finance.director@example.test'), 'hrDirector' => $user('hr.director@example.test'),
        ];
        $units = ['tech' => $unit('TECH'), 'finance' => $unit('FIN'), 'hr' => $unit('HR'), 'office' => $unit('AUTH-OFFICE')];
        $techEmployee = Employee::where('organizational_unit_id', $units['tech']->id)->firstOrFail();
        $financeEmployee = Employee::where('organizational_unit_id', $units['finance']->id)->firstOrFail();
        $hrEmployee = Employee::where('organizational_unit_id', $units['hr']->id)->firstOrFail();
        $authority = OrganizationAuthority::where('authority_office_unit_id', $units['office']->id)->firstOrFail();
        $request = MeetingRequest::create([
            'request_number' => $suffix.'-'.uniqid(), 'flow_type' => 'HIGH_AUTHORITY_INITIATED', 'created_by' => $users['secretary']->id,
            'subject' => 'جلسه آزمون تغییر زمان', 'purpose' => 'DECISION', 'priority' => 'NORMAL',
            'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $users['secretary']->id,
        ]);
        $meeting = Meeting::create([
            'meeting_request_id' => $request->id, 'meeting_number' => $suffix.'-MEETING-'.uniqid(), 'title' => $request->subject,
            'description' => 'جلسه رسمی آزمون FLOW-07', 'organizing_unit_id' => $units['office']->id, 'coordinator_id' => $users['secretary']->id,
            'chairperson_employee_id' => $users['authority']->employee_id, 'meeting_date' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '09:00', 'end_time' => '10:00', 'location' => 'تالار اصلی', 'status' => 'SCHEDULED',
        ]);
        foreach ([$units['tech'], $units['finance'], $units['hr']] as $formalUnit) MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $formalUnit->id, 'role' => 'PARTICIPATING']);
        foreach ([[$techEmployee, $units['tech']], [$financeEmployee, $units['finance']], [$hrEmployee, $units['hr']]] as [$employee, $formalUnit]) MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $formalUnit->id, 'role' => 'PARTICIPATING']);
        MeetingAuthorityContext::create([
            'meeting_id' => $meeting->id, 'organization_authority_id' => $authority->id, 'authority_type' => $authority->authority_type,
            'authority_position_id' => $authority->authority_position_id, 'authority_employee_id' => $users['authority']->employee_id,
            'authority_office_unit_id' => $authority->authority_office_unit_id,
        ]);
        (new BackfillMeetingScheduleVersions)();
        return [$meeting->fresh(['request', 'units', 'participants', 'authorityContext', 'currentScheduleVersion']), $users, $units];
    }
}
