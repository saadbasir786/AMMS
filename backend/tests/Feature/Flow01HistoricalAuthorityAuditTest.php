<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Flow01HistoricalAuthorityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_historical_creator_without_current_business_scope_cannot_see_request_in_business_list_or_dashboard(): void
    {
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id' => $tech->id,
            'subject' => 'درخواست تاریخی',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'participant_ids' => [],
        ])->assertCreated();

        $request = MeetingRequest::latest('id')->firstOrFail();
        $exec->roleScopes()->delete();

        $this->actingAs($exec, 'sanctum')->getJson('/api/meeting-requests')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($exec, 'sanctum')->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.my_requests', 0)
            ->assertJsonPath('data.pending_approval', 0)
            ->assertJsonPath('data.approved_for_scheduling', 0);

        $this->assertDatabaseHas('meeting_requests', ['id' => $request->id]);
    }

    public function test_historical_coordinator_without_current_scope_cannot_see_meeting_unless_participant(): void
    {
        [$exec, $meeting] = $this->createMeetingWithoutExecAsParticipant();
        $exec->roleScopes()->delete();

        $this->actingAs($exec, 'sanctum')->getJson('/api/meetings')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($exec, 'sanctum')->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.upcoming_meetings', 0);

        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'coordinator_id' => $exec->id]);
    }

    private function createMeetingWithoutExecAsParticipant(): array
    {
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $director = User::where('email', 'tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $employees = Employee::whereIn('employee_number', ['EMP-TECH-001', 'EMP-TECH-003'])->pluck('id')->all();
        $date = now()->addWeek()->format('Y-m-d');

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id' => $tech->id,
            'subject' => 'جلسه برای آزمون صلاحیت تاریخی',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'participant_ids' => [$employees[0]],
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();

        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-scheduling")->assertOk();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/confirm-schedule", [
            'meeting_date' => $date,
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'location' => 'اتاق جلسات تکنالوژی',
            'chairperson_employee_id' => $employees[0],
            'participant_ids' => [$employees[0]],
        ])->assertOk();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize", [
            'meeting_date' => $date,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => 'اتاق جلسات تکنالوژی',
            'chairperson_employee_id' => $employees[0],
            'agenda' => 'ممیزی',
        ])->assertOk();

        return [$exec, Meeting::where('meeting_request_id', $request->id)->firstOrFail()];
    }
}
