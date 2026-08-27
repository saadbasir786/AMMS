<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantGovernanceResolver;
use App\Support\ParticipantVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RescheduleAndParticipantChangeAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_flow07_uses_current_formal_scope_not_employee_subunit_for_peer_governance(): void
    {
        [$meeting, $tech, $finance] = $this->peerMeeting();
        $techExec = $this->user('tech.exec@example.test');
        $financeExec = $this->user('finance.exec@example.test');
        $this->grant($techExec, ['meeting.view', 'meeting-reschedule.propose', 'meeting-reschedule.confirm']);
        $this->grant($financeExec, ['meeting.view', 'meeting-reschedule.propose', 'meeting-reschedule.confirm']);

        $this->assertNotSame($tech->id, (int) $techExec->employee->organizational_unit_id, 'Fixture must prove subunit-vs-formal-unit behavior.');
        $this->assertTrue(Gate::forUser($techExec)->allows('meetingReschedulePeerPropose', $meeting));
        $this->assertTrue(Gate::forUser($financeExec)->allows('meetingReschedulePeerPropose', $meeting));
        $this->assertTrue(Gate::forUser($techExec)->allows('meetingRescheduleConfirm', $meeting));
        $this->assertFalse(Gate::forUser($financeExec)->allows('meetingRescheduleConfirm', $meeting), 'Only requester/lead Executive Management may confirm peer rescheduling.');
    }

    public function test_historical_meeting_coordinator_has_no_post_creation_view_authority_after_scope_revocation(): void
    {
        [$meeting, $tech] = $this->internalMeeting();
        $techExec = $this->user('tech.exec@example.test');
        $this->grant($techExec, ['meeting.view']);
        $this->assertTrue(Gate::forUser($techExec)->allows('view', $meeting));

        $techExec->roleScopes()->where('organizational_unit_id', $tech->id)->delete();

        $this->assertFalse(Gate::forUser($techExec->fresh())->allows('view', $meeting->fresh(['request', 'units', 'participants', 'authorityContext'])));
    }

    public function test_flow08_unit_ownership_survives_authority_controlled_origin_and_authority_side_remains_office_owned(): void
    {
        [$meeting, $tech] = $this->authorityMeeting();
        $techExec = $this->user('tech.exec@example.test');
        $techDirector = $this->user('tech.director@example.test');
        $office = $this->user('authority.office@example.test');
        $secretary = $this->user('executive.secretary@example.test');
        foreach ([$techExec, $techDirector, $office, $secretary] as $user) {
            $this->grant($user, ['meeting.view', 'authority-request.view', 'meeting-participant-change.create', 'meeting-participant-change.manage', 'meeting-participant-change.confirm']);
        }

        $resolver = app(ParticipantGovernanceResolver::class);
        $this->assertSame(['owner_type' => 'UNIT', 'owner_unit_id' => $tech->id], $resolver->actorOwner($meeting, $techExec));
        $this->assertTrue($resolver->mayPrepare($meeting, $techExec, 'UNIT', $tech->id));
        $this->assertTrue($resolver->mayConfirm($meeting, $techDirector, 'UNIT', $tech->id));
        $this->assertTrue($resolver->mayPrepare($meeting, $office, 'AUTHORITY', null));
        $this->assertTrue($resolver->mayConfirm($meeting, $office, 'AUTHORITY', null));
        $this->assertFalse($resolver->mayPrepare($meeting, $secretary, 'AUTHORITY', null));
        $this->assertFalse($resolver->mayConfirm($meeting, $secretary, 'AUTHORITY', null));
    }

    public function test_initial_participant_version_maps_subunit_member_to_formal_unit_even_in_authority_flow(): void
    {
        [$meeting, $tech] = $this->authorityMeeting();
        $techExec = $this->user('tech.exec@example.test');
        $authority = $this->user('high.authority@example.test');
        $this->assertNotSame($tech->id, (int) $techExec->employee->organizational_unit_id);

        $meeting->participants()->create([
            'employee_id' => $techExec->employee_id,
            'organizational_unit_id' => $techExec->employee->organizational_unit_id,
            'role' => 'REQUESTING',
        ]);
        $meeting->participants()->create([
            'employee_id' => $authority->employee_id,
            'organizational_unit_id' => $authority->employee->organizational_unit_id,
            'role' => 'HIGH_AUTHORITY',
        ]);

        $version = app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['units.unit', 'authorityContext', 'participants.employee.unit']), $techExec->id);
        $unitMember = $version->members->firstWhere('employee_id', $techExec->employee_id);
        $authorityMember = $version->members->firstWhere('employee_id', $authority->employee_id);

        $this->assertSame('UNIT', $unitMember->owner_type);
        $this->assertSame($tech->id, (int) $unitMember->owner_unit_id);
        $this->assertSame('AUTHORITY', $authorityMember->owner_type);
        $this->assertNull($authorityMember->owner_unit_id);
        $this->assertTrue((bool) $authorityMember->is_mandatory);
    }

    private function peerMeeting(): array
    {
        $tech = $this->unit('TECH');
        $finance = $this->unit('FIN');
        $request = MeetingRequest::create([
            'request_number' => 'AUD-F07-PEER', 'flow_type' => 'INTER_DIRECTORATE', 'requesting_unit_id' => $tech->id,
            'created_by' => $this->user('tech.exec@example.test')->id, 'subject' => 'Peer reschedule audit',
            'purpose' => 'COORDINATION', 'priority' => 'NORMAL', 'status' => 'CONVERTED_TO_MEETING',
        ]);
        $meeting = $this->meeting($request, $tech, $this->user('tech.exec@example.test'));
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $finance->id, 'role' => 'PARTICIPATING']);
        return [$meeting->fresh(['request', 'units', 'participants', 'authorityContext']), $tech, $finance];
    }

    private function internalMeeting(): array
    {
        $tech = $this->unit('TECH');
        $request = MeetingRequest::create([
            'request_number' => 'AUD-F07-INTERNAL', 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $tech->id,
            'created_by' => $this->user('tech.exec@example.test')->id, 'subject' => 'Internal audit', 'purpose' => 'COORDINATION',
            'priority' => 'NORMAL', 'status' => 'CONVERTED_TO_MEETING',
        ]);
        $meeting = $this->meeting($request, $tech, $this->user('tech.exec@example.test'));
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING']);
        return [$meeting->fresh(['request', 'units', 'participants', 'authorityContext']), $tech];
    }

    private function authorityMeeting(): array
    {
        $tech = $this->unit('TECH');
        $office = $this->unit('AUTH-OFFICE');
        $request = MeetingRequest::create([
            'request_number' => 'AUD-F08-AUTH', 'flow_type' => 'DIRECTORATE_WITH_HIGH_AUTHORITY', 'requesting_unit_id' => $tech->id,
            'created_by' => $this->user('tech.exec@example.test')->id, 'subject' => 'Authority participant audit',
            'purpose' => 'DECISION', 'priority' => 'NORMAL', 'status' => 'CONVERTED_TO_MEETING',
        ]);
        $meeting = $this->meeting($request, $tech, $this->user('executive.secretary@example.test'));
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING']);
        $authority = OrganizationAuthority::where('is_primary', true)->firstOrFail();
        MeetingAuthorityContext::create([
            'meeting_id' => $meeting->id, 'organization_authority_id' => $authority->id, 'authority_type' => $authority->authority_type,
            'authority_position_id' => $authority->authority_position_id, 'authority_employee_id' => $this->user('high.authority@example.test')->employee_id,
            'authority_office_unit_id' => $office->id,
        ]);
        return [$meeting->fresh(['request', 'units', 'participants', 'authorityContext']), $tech];
    }

    private function meeting(MeetingRequest $request, OrganizationalUnit $organizing, User $coordinator): Meeting
    {
        return Meeting::create([
            'meeting_request_id' => $request->id, 'meeting_number' => 'AUD-MT-'.uniqid(), 'title' => $request->subject,
            'organizing_unit_id' => $organizing->id, 'coordinator_id' => $coordinator->id,
            'chairperson_employee_id' => $this->user('tech.director@example.test')->employee_id,
            'meeting_date' => now()->addDays(10)->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '10:00',
            'location' => 'Audit Hall', 'status' => 'SCHEDULED',
        ]);
    }

    private function grant(User $user, array $permissions): void
    {
        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'sanctum']);
            $user->givePermissionTo($permission);
        }
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function unit(string $code): OrganizationalUnit
    {
        return OrganizationalUnit::where('code', $code)->firstOrFail();
    }
}
