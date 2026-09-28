<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Employee;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionCaseVersion;
use App\Models\MeetingRequest;
use App\Models\MeetingParticipantChangeCase;
use App\Models\MeetingParticipantVersion;
use App\Models\MeetingParticipantVersionMember;
use App\Models\MeetingUnit;
use App\Models\MeetingScheduleVersion;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MeetingVersionInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authorized_user_can_view_and_compare_immutable_schedule_versions_in_both_directions(): void
    {
        $actor = User::where('email', 'tech.director@example.test')->firstOrFail();
        $this->grant($actor, 'meeting.view');
        $this->grant($actor, 'meeting-reschedule.view-history');
        [$meeting, $otherMeeting] = [$this->meeting($actor, 'VERSION-MEETING-A'), $this->meeting($actor, 'VERSION-MEETING-B')];
        $first = $this->scheduleVersion($meeting, $actor, 1, '2026-10-01', '09:00', '10:00', 'Room A');
        $this->scheduleVersion($meeting, $actor, 2, '2026-10-02', '10:00', '11:00', 'Room A');
        $fourth = $this->scheduleVersion($meeting, $actor, 4, '2026-10-04', '11:00', '12:00', 'Room B');
        $foreign = $this->scheduleVersion($otherMeeting, $actor, 1, '2026-10-05', '09:00', '10:00', 'Room C');
        $meeting->update(['current_schedule_version_id' => $fourth->id]);
        $this->assertTrue($actor->isActive());
        $this->assertTrue($actor->can('meeting.view'));
        $this->assertTrue(Gate::forUser($actor)->allows('view', $meeting->fresh(['request', 'units', 'participants', 'authorityContext'])));
        $this->assertTrue(Gate::forUser($actor)->allows('meetingRescheduleViewHistory', $meeting->fresh(['request', 'units', 'participants', 'authorityContext'])));

        $beforeCount = MeetingScheduleVersion::count();
        $beforePointer = $meeting->fresh()->current_schedule_version_id;
        $this->actingAs($actor, 'sanctum')->getJson('/api/meetings/'.$meeting->id.'/versions/schedule')
            ->assertOk()->assertJsonPath('data.0.current', true);
        $this->actingAs($actor, 'sanctum')->getJson('/api/meetings/'.$meeting->id.'/versions/schedule/'.$first->id)
            ->assertOk()->assertJsonPath('data.current', false)->assertJsonPath('data.version.version_number', 1);
        $this->getJson('/api/meetings/'.$meeting->id.'/versions/schedule/compare?from_id='.$first->id.'&to_id='.$fourth->id)
            ->assertOk()->assertJsonPath('data.from.version_number', 1)->assertJsonPath('data.to.version_number', 4)
            ->assertJsonPath('data.comparison.fields.0.changed', true);
        $this->getJson('/api/meetings/'.$meeting->id.'/versions/schedule/compare?from_id='.$fourth->id.'&to_id='.$first->id)
            ->assertOk()->assertJsonPath('data.from.version_number', 4)->assertJsonPath('data.to.version_number', 1);
        $this->getJson('/api/meetings/'.$meeting->id.'/versions/schedule/'.$foreign->id)->assertNotFound();
        $this->assertSame($beforeCount, MeetingScheduleVersion::count());
        $this->assertSame($beforePointer, $meeting->fresh()->current_schedule_version_id);
    }

    public function test_user_without_meeting_history_authority_cannot_retrieve_a_version_directly(): void
    {
        $actor = User::where('email', 'system.admin@example.test')->firstOrFail();
        $this->grant($actor, 'meeting.view');
        $this->grant($actor, 'meeting-reschedule.view-history');
        $meeting = $this->meeting(User::where('email', 'tech.director@example.test')->firstOrFail(), 'VERSION-PRIVATE');
        $version = $this->scheduleVersion($meeting, $actor, 1, '2026-10-01', '09:00', '10:00', 'Private Room');

        $this->actingAs($actor, 'sanctum')->getJson('/api/meetings/'.$meeting->id.'/versions/schedule/'.$version->id)->assertForbidden();
    }

    public function test_participant_comparison_includes_intermediate_add_remove_and_replacement_operations_chronologically(): void
    {
        $actor = User::where('email', 'tech.director@example.test')->firstOrFail();
        $this->grant($actor, 'meeting.view');
        $this->grant($actor, 'meeting-participant-change.view-history');
        $meeting = $this->meeting($actor, 'VERSION-PARTICIPANTS');
        $employees = Employee::whereIn('employee_number', ['EMP-TECH-001', 'EMP-TECH-002', 'EMP-TECH-003', 'EMP-TECH-004'])->get()->keyBy('employee_number');
        $unitId = (int) OrganizationalUnit::where('code', 'TECH')->value('id');

        $version1 = $this->participantVersion($meeting, $actor, 1, [$employees['EMP-TECH-001'], $employees['EMP-TECH-002']], $unitId);
        $case2 = $this->participantChangeCase($meeting, $actor, $version1, $unitId);
        $case2->items()->create(['operation' => 'ADD', 'new_employee_id' => $employees['EMP-TECH-003']->id, 'participant_role' => 'MEMBER']);
        $version2 = $this->participantVersion($meeting, $actor, 2, [$employees['EMP-TECH-001'], $employees['EMP-TECH-002'], $employees['EMP-TECH-003']], $unitId, $case2);

        $case3 = $this->participantChangeCase($meeting, $actor, $version2, $unitId);
        $case3->items()->create(['operation' => 'REMOVE', 'old_employee_id' => $employees['EMP-TECH-002']->id]);
        $version3 = $this->participantVersion($meeting, $actor, 3, [$employees['EMP-TECH-001'], $employees['EMP-TECH-003']], $unitId, $case3);

        $case4 = $this->participantChangeCase($meeting, $actor, $version3, $unitId);
        $case4->items()->create(['operation' => 'REPLACE', 'old_employee_id' => $employees['EMP-TECH-003']->id, 'new_employee_id' => $employees['EMP-TECH-004']->id, 'participant_role' => 'MEMBER']);
        $version4 = $this->participantVersion($meeting, $actor, 4, [$employees['EMP-TECH-001'], $employees['EMP-TECH-004']], $unitId, $case4);
        $meeting->forceFill(['current_participant_version_id' => $version4->id])->save();

        $this->actingAs($actor, 'sanctum')->getJson('/api/meetings/'.$meeting->id.'/versions/participants/compare?from_id='.$version1->id.'&to_id='.$version4->id)
            ->assertOk()
            ->assertJsonPath('data.comparison.history.0.type', 'add')
            ->assertJsonPath('data.comparison.history.0.version_number', 2)
            ->assertJsonPath('data.comparison.history.1.type', 'remove')
            ->assertJsonPath('data.comparison.history.1.version_number', 3)
            ->assertJsonPath('data.comparison.history.2.type', 'replace')
            ->assertJsonPath('data.comparison.history.2.version_number', 4)
            ->assertJsonPath('data.comparison.history.2.old.name', $employees['EMP-TECH-003']->full_name)
            ->assertJsonPath('data.comparison.history.2.new.name', $employees['EMP-TECH-004']->full_name);

        $this->getJson('/api/meetings/'.$meeting->id.'/versions/participants/compare?from_id='.$version4->id.'&to_id='.$version1->id)
            ->assertOk()
            ->assertJsonPath('data.from.version_number', 4)
            ->assertJsonPath('data.to.version_number', 1)
            ->assertJsonPath('data.comparison.history.0.type', 'replace')
            ->assertJsonPath('data.comparison.history.0.version_number', 4)
            ->assertJsonPath('data.comparison.history.0.old.name', $employees['EMP-TECH-004']->full_name)
            ->assertJsonPath('data.comparison.history.0.new.name', $employees['EMP-TECH-003']->full_name)
            ->assertJsonPath('data.comparison.history.1.type', 'add')
            ->assertJsonPath('data.comparison.history.2.type', 'remove');
    }

    public function test_authorized_user_can_view_and_compare_exception_versions_as_read_only_business_snapshots(): void
    {
        $actor = User::where('email', 'tech.director@example.test')->firstOrFail();
        $this->grant($actor, 'meeting.exception.view');
        $this->grant($actor, 'meeting.exception.history.view');
        $meeting = $this->meeting($actor, 'VERSION-EXCEPTION');
        $case = MeetingExceptionCase::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'meeting_id' => $meeting->id, 'case_type' => MeetingExceptionCase::POSTPONEMENT,
            'status' => MeetingExceptionCase::UNDER_REVIEW, 'governance_mode' => 'INTERNAL_SINGLE_UNIT', 'baseline_meeting_status' => 'SCHEDULED',
            'created_by_user_id' => $actor->id,
        ]);
        $first = MeetingExceptionCaseVersion::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'exception_case_id' => $case->id, 'version_number' => 1, 'reason' => 'Initial reason',
            'baseline_meeting_status' => 'SCHEDULED', 'prepared_by_user_id' => $actor->id, 'created_at' => now(),
        ]);
        $second = MeetingExceptionCaseVersion::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(), 'exception_case_id' => $case->id, 'version_number' => 2, 'reason' => 'Updated reason',
            'administrative_note' => 'Reviewed', 'baseline_meeting_status' => 'SCHEDULED', 'prepared_by_user_id' => $actor->id, 'created_at' => now(),
        ]);
        $case->forceFill(['current_version_id' => $second->id])->save();

        $this->actingAs($actor, 'sanctum')->getJson('/api/meeting-exceptions/'.$case->id.'/versions/'.$first->id)
            ->assertOk()->assertJsonPath('data.current', false)->assertJsonPath('data.version.reason', 'Initial reason');
        $this->getJson('/api/meeting-exceptions/'.$case->id.'/versions/compare?from_id='.$second->id.'&to_id='.$first->id)
            ->assertOk()->assertJsonPath('data.from.version_number', 2)->assertJsonPath('data.to.version_number', 1)
            ->assertJsonPath('data.comparison.fields.0.from', 'Updated reason')->assertJsonPath('data.comparison.fields.0.to', 'Initial reason');
    }

    private function meeting(User $coordinator, string $key): Meeting
    {
        $unit = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => $key.'-REQ', 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $coordinator->id, 'subject' => $key, 'purpose' => 'COORDINATION', 'priority' => 'NORMAL', 'status' => 'CONVERTED_TO_MEETING']);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => $key, 'title' => $key, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $coordinator->id, 'chairperson_employee_id' => $coordinator->employee_id, 'meeting_date' => '2026-10-01', 'start_time' => '09:00', 'end_time' => '10:00', 'location' => 'Room A', 'status' => 'SCHEDULED']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'REQUESTING']);
        return $meeting;
    }

    private function scheduleVersion(Meeting $meeting, User $actor, int $number, string $date, string $start, string $end, string $location): MeetingScheduleVersion
    {
        return MeetingScheduleVersion::create(['meeting_id' => $meeting->id, 'version_number' => $number, 'meeting_date' => $date, 'start_time' => $start, 'end_time' => $end, 'location' => $location, 'created_by_user_id' => $actor->id]);
    }

    private function participantVersion(Meeting $meeting, User $actor, int $number, array $employees, int $unitId, ?MeetingParticipantChangeCase $case = null): MeetingParticipantVersion
    {
        $version = MeetingParticipantVersion::create(['meeting_id' => $meeting->id, 'version_number' => $number, 'source_change_case_id' => $case?->id, 'created_by_user_id' => $actor->id]);
        foreach ($employees as $employee) {
            MeetingParticipantVersionMember::create(['participant_version_id' => $version->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id ?: $unitId, 'participant_role' => 'MEMBER', 'owner_type' => 'UNIT', 'owner_unit_id' => $unitId, 'is_mandatory' => false]);
        }
        return $version;
    }

    private function participantChangeCase(Meeting $meeting, User $actor, MeetingParticipantVersion $baseline, int $unitId): MeetingParticipantChangeCase
    {
        return MeetingParticipantChangeCase::create(['meeting_id' => $meeting->id, 'owner_type' => 'UNIT', 'owner_unit_id' => $unitId, 'baseline_participant_version_id' => $baseline->id, 'initiated_by_user_id' => $actor->id, 'reason' => 'Version comparison fixture', 'status' => 'CONFIRMED']);
    }

    private function grant(User $user, string $permission): void
    {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']));
    }
}
