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

    public function test_request_preferences_do_not_become_scheduled_values_automatically(): void
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $director = User::where('email','tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();
        $employees = Employee::whereIn('employee_number',['EMP-TECH-001','EMP-TECH-003'])->pluck('id')->all();
        $preferredDate = now()->addDays(3)->format('Y-m-d');
        $scheduledDate = now()->addDays(8)->format('Y-m-d');

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id'=>$tech->id,
            'subject'=>'تفکیک پیشنهاد از زمان‌بندی',
            'purpose'=>'COORDINATION',
            'priority'=>'NORMAL',
            'preferred_date'=>$preferredDate,
            'preferred_start_time'=>'08:30',
            'estimated_duration_minutes'=>45,
            'proposed_location'=>'دفتر پیشنهادی',
            'participant_ids'=>$employees,
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-scheduling")->assertOk();

        $request->refresh();
        $this->assertSame($preferredDate, $request->preferred_date->format('Y-m-d'));
        $this->assertNull($request->scheduled_date);
        $this->assertNull($request->scheduled_start_time);
        $this->assertNull($request->scheduled_location);

        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/confirm-schedule", [
            'meeting_date'=>$scheduledDate,
            'start_time'=>'14:00',
            'duration_minutes'=>90,
            'location'=>'تالار رسمی جلسه',
            'chairperson_employee_id'=>$employees[0],
            'participant_ids'=>$employees,
        ])->assertOk();

        $request->refresh();
        $this->assertSame($preferredDate, $request->preferred_date->format('Y-m-d'));
        $this->assertSame('08:30', substr((string) $request->preferred_start_time, 0, 5));
        $this->assertSame(45, (int) $request->estimated_duration_minutes);
        $this->assertSame('دفتر پیشنهادی', $request->proposed_location);
        $this->assertSame($scheduledDate, $request->scheduled_date->format('Y-m-d'));
        $this->assertSame('14:00', substr((string) $request->scheduled_start_time, 0, 5));
        $this->assertSame(90, (int) $request->scheduled_duration_minutes);
        $this->assertSame('تالار رسمی جلسه', $request->scheduled_location);
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

    public function test_finalize_uses_scheduled_values_exactly(): void
    {
        [$request, $meeting] = $this->createFlow01Meeting();

        $this->assertSame($request->scheduled_date->format('Y-m-d'), $meeting->meeting_date->format('Y-m-d'));
        $this->assertSame(substr((string) $request->scheduled_start_time, 0, 5), substr((string) $meeting->start_time, 0, 5));
        $this->assertSame($request->scheduled_location, $meeting->location);
        $this->assertSame((int) $request->scheduled_chairperson_employee_id, (int) $meeting->chairperson_employee_id);
    }

    public function test_flow01_generic_workflow_endpoints_reject_other_flow_types(): void
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();
        $request = MeetingRequest::create([
            'flow_type'=>'INTER_DIRECTORATE',
            'requesting_unit_id'=>$tech->id,
            'created_by'=>$exec->id,
            'subject'=>'درخواست غیر FLOW-01',
            'purpose'=>'COORDINATION',
            'priority'=>'NORMAL',
            'status'=>'DRAFT',
        ]);

        $this->actingAs($exec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/submit")
            ->assertStatus(422)
            ->assertJsonPath('code', 'FLOW_ENDPOINT_MISMATCH');

        $this->assertSame('DRAFT', $request->fresh()->status);
    }

    public function test_flow01_creator_cannot_continue_after_executive_scope_is_revoked(): void
    {
        $exec = User::where('email','tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code','TECH')->firstOrFail();

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id'=>$tech->id,
            'subject'=>'آزمون سلب صلاحیت جاری',
            'purpose'=>'COORDINATION',
            'priority'=>'NORMAL',
            'participant_ids'=>[],
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();

        $exec->roleScopes()->delete();

        $this->actingAs($exec, 'sanctum')->putJson("/api/meeting-requests/{$request->id}", [
            'subject'=>'تغییر غیرمجاز پس از سلب صلاحیت',
            'purpose'=>'COORDINATION',
            'priority'=>'NORMAL',
        ])->assertForbidden();

        $this->actingAs($exec, 'sanctum')
            ->postJson("/api/meeting-requests/{$request->id}/submit")
            ->assertForbidden();

        $this->assertSame('آزمون سلب صلاحیت جاری', $request->fresh()->subject);
        $this->assertSame('DRAFT', $request->fresh()->status);
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
