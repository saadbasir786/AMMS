<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationFlow01AuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_system_admin_business_dashboard_and_directories_are_empty(): void
    {
        [$request, $meeting] = $this->createFlow01Meeting();
        $system = User::where('email', 'system.admin@example.test')->firstOrFail();

        $dashboard = $this->actingAs($system, 'sanctum')->getJson('/api/dashboard')->assertOk();
        $dashboard->assertJsonPath('data.my_requests', 0)
            ->assertJsonPath('data.returned', 0)
            ->assertJsonPath('data.pending_approval', 0)
            ->assertJsonPath('data.approved_for_scheduling', 0)
            ->assertJsonPath('data.upcoming_meetings', 0);

        $this->actingAs($system, 'sanctum')->getJson('/api/approvals')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($system, 'sanctum')->getJson('/api/meetings')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($system, 'sanctum')->getJson('/api/meeting-requests')->assertOk()->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('meeting_requests', ['id' => $request->id]);
        $this->assertDatabaseHas('meetings', ['id' => $meeting->id]);
    }

    public function test_unrelated_directorate_does_not_receive_flow01_dashboard_counts(): void
    {
        $this->createFlow01Meeting();
        $financeDirector = User::where('email', 'finance.director@example.test')->firstOrFail();

        $dashboard = $this->actingAs($financeDirector, 'sanctum')->getJson('/api/dashboard')->assertOk();
        $dashboard->assertJsonPath('data.pending_approval', 0)
            ->assertJsonPath('data.approved_for_scheduling', 0)
            ->assertJsonPath('data.upcoming_meetings', 0);
    }

    public function test_finalize_rejects_any_change_to_confirmed_schedule_snapshot(): void
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $director = User::where('email','tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();
        $employees = Employee::whereIn('employee_number',['EMP-TECH-001','EMP-TECH-003'])->pluck('id')->all();

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id'=>$tech->id,
            'subject'=>'قفل زمان‌بندی نهایی',
            'purpose'=>'COORDINATION',
            'priority'=>'NORMAL',
            'participant_ids'=>$employees,
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-scheduling")->assertOk();

        $date = now()->addWeek()->format('Y-m-d');
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/confirm-schedule", [
            'meeting_date'=>$date,
            'start_time'=>'09:00',
            'duration_minutes'=>60,
            'location'=>'اتاق جلسات تکنالوژی',
            'chairperson_employee_id'=>$employees[0],
            'participant_ids'=>$employees,
        ])->assertOk();

        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize", [
            'meeting_date'=>$date,
            'start_time'=>'09:00',
            'end_time'=>'11:00',
            'location'=>'محل تغییر یافته',
            'chairperson_employee_id'=>$employees[1],
            'agenda'=>'آزمون عدم تغییر',
        ])->assertStatus(422)->assertJsonPath('code', 'CONFIRMED_SCHEDULE_MISMATCH');

        $this->assertSame('SCHEDULE_CONFIRMED', $request->fresh()->status);
        $this->assertSame(0, Meeting::where('meeting_request_id', $request->id)->count());
    }

    public function test_finalize_uses_confirmed_schedule_exactly(): void
    {
        [$request, $meeting] = $this->createFlow01Meeting();

        $this->assertSame($request->preferred_date->format('Y-m-d'), $meeting->meeting_date->format('Y-m-d'));
        $this->assertSame(substr((string) $request->preferred_start_time, 0, 5), substr((string) $meeting->start_time, 0, 5));
        $this->assertSame($request->proposed_location, $meeting->location);
        $this->assertSame((int) $request->confirmed_chairperson_employee_id, (int) $meeting->chairperson_employee_id);
    }

    private function createFlow01Meeting(): array
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $director = User::where('email','tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();
        $employees = Employee::whereIn('employee_number',['EMP-TECH-001','EMP-TECH-003'])->pluck('id')->all();
        $date = now()->addWeek()->format('Y-m-d');

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id'=>$tech->id,
            'subject'=>'جلسه ممیزی FLOW-01',
            'purpose'=>'COORDINATION',
            'priority'=>'NORMAL',
            'participant_ids'=>$employees,
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-scheduling")->assertOk();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/confirm-schedule", [
            'meeting_date'=>$date,
            'start_time'=>'09:00',
            'duration_minutes'=>60,
            'location'=>'اتاق جلسات تکنالوژی',
            'chairperson_employee_id'=>$employees[0],
            'participant_ids'=>$employees,
        ])->assertOk();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize", [
            'meeting_date'=>$date,
            'start_time'=>'09:00',
            'end_time'=>'10:00',
            'location'=>'اتاق جلسات تکنالوژی',
            'chairperson_employee_id'=>$employees[0],
            'agenda'=>'ممیزی',
        ])->assertOk();

        return [$request->fresh(), Meeting::where('meeting_request_id', $request->id)->firstOrFail()];
    }
}
