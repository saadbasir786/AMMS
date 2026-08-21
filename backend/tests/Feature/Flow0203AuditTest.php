<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\MeetingScheduleProposal;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Flow0203AuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_flow02_confirmed_schedule_is_separate_from_request_preferences_and_finalize_cannot_override_it(): void
    {
        [$request, $techExec, $techDirector, $financeExec, $financeDirector, $tech, $finance, $techEmployee, $financeEmployee] = $this->flow02ToScheduling();

        $preferredDate = now()->addDays(2)->format('Y-m-d');
        $request->update([
            'preferred_date' => $preferredDate,
            'preferred_start_time' => '08:15',
            'estimated_duration_minutes' => 45,
            'proposed_location' => 'محل پیشنهادی اولیه',
        ]);

        $scheduledDate = now()->addDays(8)->format('Y-m-d');
        $proposal = $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/schedule-proposals", [
            'proposed_by_unit_id' => $tech->id,
            'meeting_date' => $scheduledDate,
            'start_time' => '14:00',
            'end_time' => '15:30',
            'location' => 'تالار رسمی مشترک',
        ])->assertOk();
        $proposalId = $proposal->json('data.schedule_proposals.0.id') ?: MeetingScheduleProposal::where('meeting_request_id', $request->id)->latest('id')->value('id');
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/schedule-proposals/{$proposalId}/accept")->assertOk();

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/participant-nominations", ['nominated_by_unit_id' => $tech->id, 'employee_id' => $techEmployee->id])->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/participant-nominations", ['nominated_by_unit_id' => $finance->id, 'employee_id' => $financeEmployee->id])->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/requester-participants/confirm")->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-participants/confirm")->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/confirm-inter-directorate-schedule")->assertOk();

        $request->refresh();
        $this->assertSame($preferredDate, $request->preferred_date->format('Y-m-d'));
        $this->assertSame('08:15', substr((string) $request->preferred_start_time, 0, 5));
        $this->assertSame(45, (int) $request->estimated_duration_minutes);
        $this->assertSame('محل پیشنهادی اولیه', $request->proposed_location);
        $this->assertSame($scheduledDate, $request->scheduled_date->format('Y-m-d'));
        $this->assertSame('14:00', substr((string) $request->scheduled_start_time, 0, 5));
        $this->assertSame(90, (int) $request->scheduled_duration_minutes);
        $this->assertSame('تالار رسمی مشترک', $request->scheduled_location);

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize-inter-directorate", [
            'chairperson_employee_id' => $techEmployee->id,
            'location' => 'محل دستکاری‌شده',
            'end_time' => '18:00',
            'agenda' => 'آجندا',
        ])->assertUnprocessable();
        $this->assertSame(0, Meeting::where('meeting_request_id', $request->id)->count());

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize-inter-directorate", [
            'chairperson_employee_id' => $techEmployee->id,
            'agenda' => 'آجندا',
        ])->assertOk();

        $meeting = Meeting::where('meeting_request_id', $request->id)->firstOrFail();
        $this->assertSame($scheduledDate, $meeting->meeting_date->format('Y-m-d'));
        $this->assertSame('14:00', substr((string) $meeting->start_time, 0, 5));
        $this->assertSame('15:30', substr((string) $meeting->end_time, 0, 5));
        $this->assertSame('تالار رسمی مشترک', $meeting->location);
    }

    public function test_flow02_material_change_after_target_return_requires_requester_reapproval(): void
    {
        [$request, $techExec, $techDirector, $financeExec, $financeDirector] = $this->flow02ToTargetReview();
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-return", ['reason' => 'موضوع باید واضح‌تر شود.'])->assertOk();

        $this->actingAs($techExec, 'sanctum')->putJson("/api/meeting-requests/{$request->id}", [
            'subject' => 'موضوع اساسی جدید',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
        ])->assertOk()->assertJsonPath('data.status', 'PENDING_REQUESTER_APPROVAL');

        $request->refresh();
        $this->assertNull($request->requester_approved_by);
        $this->assertNull($request->target_approved_by);
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/resubmit-to-target")->assertForbidden();
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/requester-approve")->assertOk()->assertJsonPath('data.status', 'SUBMITTED_TO_TARGET');
    }

    public function test_flow03_confirmed_schedule_is_immutable_and_confirmed_participant_list_cannot_be_extended(): void
    {
        [$request, $techExec, $financeExec, $hrExec, $tech, $finance, $hr, $techEmployee, $financeEmployee, $hrEmployee] = $this->flow03ToScheduling();

        $request->update([
            'preferred_date' => now()->addDays(2)->format('Y-m-d'),
            'preferred_start_time' => '08:00',
            'estimated_duration_minutes' => 30,
            'proposed_location' => 'مرجع اولیه',
        ]);
        $preferredDate = $request->preferred_date->format('Y-m-d');
        $scheduledDate = now()->addDays(10)->format('Y-m-d');

        $response = $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/schedule-proposals", [
            'proposed_by_unit_id' => $tech->id,
            'meeting_date' => $scheduledDate,
            'start_time' => '13:00',
            'end_time' => '14:15',
            'location' => 'تالار سه‌جانبه',
        ])->assertOk();
        $proposalId = $response->json('data.schedule_proposals.0.id') ?: MeetingScheduleProposal::where('meeting_request_id', $request->id)->latest('id')->value('id');
        foreach ([[$financeExec, $finance], [$hrExec, $hr]] as [$actor, $unit]) {
            $this->actingAs($actor, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/schedule-proposals/{$proposalId}/accept", ['unit_id' => $unit->id])->assertOk();
        }

        foreach ([[$techExec, $tech, $techEmployee], [$financeExec, $finance, $financeEmployee], [$hrExec, $hr, $hrEmployee]] as [$actor, $unit, $employee]) {
            $this->actingAs($actor, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/units/{$unit->id}/participants", ['organizational_unit_id' => $unit->id, 'employee_id' => $employee->id])->assertOk();
            $this->actingAs($actor, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/units/{$unit->id}/participants/confirm")->assertOk();
        }

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/confirm-multi-schedule")->assertOk();
        $request->refresh();
        $this->assertSame($preferredDate, $request->preferred_date->format('Y-m-d'));
        $this->assertSame('08:00', substr((string) $request->preferred_start_time, 0, 5));
        $this->assertSame('مرجع اولیه', $request->proposed_location);
        $this->assertSame($scheduledDate, $request->scheduled_date->format('Y-m-d'));
        $this->assertSame('13:00', substr((string) $request->scheduled_start_time, 0, 5));
        $this->assertSame(75, (int) $request->scheduled_duration_minutes);
        $this->assertSame('تالار سه‌جانبه', $request->scheduled_location);

        $anotherTechEmployee = Employee::where('employee_number', 'EMP-TECH-004')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/units/{$tech->id}/participants", [
            'organizational_unit_id' => $tech->id,
            'employee_id' => $anotherTechEmployee->id,
        ])->assertStatus(422)->assertJsonPath('code', 'PARTICIPANTS_ALREADY_CONFIRMED');

        $chair = Employee::where('employee_number', 'EMP-TECH-001')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize-multi", [
            'chairperson_employee_id' => $chair->id,
            'location' => 'محل دستکاری‌شده',
            'end_time' => '18:00',
            'agenda' => 'آجندا',
        ])->assertUnprocessable();

        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize-multi", [
            'chairperson_employee_id' => $chair->id,
            'agenda' => 'آجندا',
        ])->assertOk();
        $meeting = Meeting::where('meeting_request_id', $request->id)->firstOrFail();
        $this->assertSame($scheduledDate, $meeting->meeting_date->format('Y-m-d'));
        $this->assertSame('13:00', substr((string) $meeting->start_time, 0, 5));
        $this->assertSame('14:15', substr((string) $meeting->end_time, 0, 5));
        $this->assertSame('تالار سه‌جانبه', $meeting->location);
    }

    public function test_system_admin_has_no_flow02_or_flow03_business_coordination_authority(): void
    {
        $system = User::where('email', 'system.admin@example.test')->firstOrFail();
        [$flow02] = $this->flow02ToScheduling();
        $this->actingAs($system, 'sanctum')->postJson("/api/meeting-requests/{$flow02->id}/schedule-proposals", [
            'proposed_by_unit_id' => $flow02->requesting_unit_id,
            'meeting_date' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => 'نامجاز',
        ])->assertForbidden();

        [$flow03] = $this->flow03ToScheduling();
        $this->actingAs($system, 'sanctum')->postJson("/api/meeting-requests/{$flow03->id}/multi/schedule-proposals", [
            'proposed_by_unit_id' => $flow03->requesting_unit_id,
            'meeting_date' => now()->addDays(5)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'location' => 'نامجاز',
        ])->assertForbidden();
    }

    private function flow02ToTargetReview(): array
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $financeDirector = User::where('email', 'finance.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $techEmployee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $financeEmployee = Employee::where('employee_number', 'EMP-FIN-002')->firstOrFail();

        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/inter-directorate', [
            'requesting_unit_id' => $tech->id,
            'target_unit_id' => $finance->id,
            'subject' => 'ممیزی FLOW-02',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'participant_ids' => [$techEmployee->id, $financeEmployee->id],
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-inter-directorate")->assertOk();
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/requester-approve")->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-review")->assertOk();
        return [$request, $techExec, $techDirector, $financeExec, $financeDirector, $tech, $finance, $techEmployee, $financeEmployee];
    }

    private function flow02ToScheduling(): array
    {
        $state = $this->flow02ToTargetReview();
        [$request, $techExec, $techDirector, $financeExec, $financeDirector] = $state;
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/target-approve")->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-inter-directorate-scheduling")->assertOk();
        return $state;
    }

    private function flow03ToScheduling(): array
    {
        $techExec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $techDirector = User::where('email', 'tech.director@example.test')->firstOrFail();
        $financeExec = User::where('email', 'finance.exec@example.test')->firstOrFail();
        $financeDirector = User::where('email', 'finance.director@example.test')->firstOrFail();
        $hrExec = User::where('email', 'hr.exec@example.test')->firstOrFail();
        $hrDirector = User::where('email', 'hr.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
        $techEmployee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $financeEmployee = Employee::where('employee_number', 'EMP-FIN-002')->firstOrFail();
        $hrEmployee = Employee::where('employee_number', 'EMP-HR-002')->firstOrFail();

        $this->actingAs($techExec, 'sanctum')->postJson('/api/meeting-requests/multi-directorate', [
            'requesting_unit_id' => $tech->id,
            'target_unit_ids' => [$finance->id, $hr->id],
            'subject' => 'ممیزی FLOW-03',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'participant_ids' => [$techEmployee->id, $financeEmployee->id, $hrEmployee->id],
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit-multi")->assertOk();
        $this->actingAs($techDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/requester-approve")->assertOk();
        $this->actingAs($financeExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$finance->id}/review")->assertOk();
        $this->actingAs($hrExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$hr->id}/review")->assertOk();
        $this->actingAs($financeDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$finance->id}/approve")->assertOk();
        $this->actingAs($hrDirector, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/multi/target-units/{$hr->id}/approve")->assertOk();
        $this->actingAs($techExec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/start-multi-scheduling")->assertOk();

        return [$request, $techExec, $financeExec, $hrExec, $tech, $finance, $hr, $techEmployee, $financeEmployee, $hrEmployee];
    }
}
