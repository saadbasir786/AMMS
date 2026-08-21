<?php

namespace Tests\Feature;

use App\Enums\MeetingRequestStatus;
use App\Models\Employee;
use App\Models\MeetingRequest;
use LogicException;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class Prompt01AFinalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_permission_matrix_and_seeded_assignments_are_present(): void
    {
        $this->assertGreaterThanOrEqual(28, Permission::where('guard_name', 'sanctum')->count());
        $this->assertGreaterThanOrEqual(5, Role::where('guard_name', 'sanctum')->count());
        $this->assertTrue(Permission::where(['name' => 'authority-request.decide', 'guard_name' => 'sanctum'])->exists());
        $this->assertTrue(Role::where(['name' => 'HIGH_AUTHORITY', 'guard_name' => 'sanctum'])->exists());

        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $this->assertTrue($exec->can('meeting-request.submit'));
        $this->assertTrue($exec->hasScopedRole('EXECUTIVE_MANAGEMENT', OrganizationalUnit::where('code', 'TECH')->value('id')));
        $this->assertFalse($exec->can('meeting-request.approve'));
    }

    public function test_permission_is_required_in_addition_to_valid_scope(): void
    {
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        Role::findByName('EXECUTIVE_MANAGEMENT', 'sanctum')->revokePermissionTo('meeting-request.create');

        $this->assertTrue($exec->hasScopedRole('EXECUTIVE_MANAGEMENT', $tech->id));
        $this->assertFalse($exec->can('meeting-request.create'));
        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id' => $tech->id, 'subject' => 'نباید ثبت شود', 'purpose' => 'COORDINATION', 'priority' => 'NORMAL', 'participant_ids' => [],
        ])->assertStatus(403);
    }

    public function test_directorate_scope_is_enforced_even_when_role_exists(): void
    {
        $financeDirector = User::where('email', 'finance.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();

        $this->assertTrue($financeDirector->hasRole('DIRECTOR', 'sanctum'));
        $this->assertFalse($financeDirector->hasScopedRole('DIRECTOR', $tech->id));
    }

    public function test_a_request_cannot_be_approved_twice_or_rejected_request_approved(): void
    {
        [$request, $exec, $director] = $this->createAndSubmitTechnologyRequest();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertStatus(403);

        [$rejected, $exec, $director] = $this->createAndSubmitTechnologyRequest('درخواست رد شده');
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$rejected->id}/reject", ['reason' => 'نیاز به اصلاح اساسی'])->assertOk();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$rejected->id}/approve")->assertStatus(403);
        $this->assertSame(MeetingRequestStatus::REJECTED->value, $rejected->fresh()->status);
    }

    public function test_rejected_request_cannot_start_scheduling_and_meeting_requires_schedule_confirmation(): void
    {
        [$rejected, $exec, $director] = $this->createAndSubmitTechnologyRequest('رد و زمان‌بندی نشود');
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$rejected->id}/reject", ['reason' => 'رد شد'])->assertOk();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$rejected->id}/start-scheduling")->assertStatus(403);

        [$request, $exec, $director] = $this->createAndSubmitTechnologyRequest('بدون تأیید زمان‌بندی');
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/approve")->assertOk();
        $employee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/finalize", [
            'meeting_date' => now()->addWeek()->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '10:00',
            'location' => 'اتاق جلسات', 'chairperson_employee_id' => $employee->id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('meetings', ['meeting_request_id' => $request->id]);
    }

    public function test_audit_history_cannot_be_updated_or_deleted_by_application_models(): void
    {
        [$request, $exec] = $this->createAndSubmitTechnologyRequest('تاریخچه غیرقابل تغییر');
        $action = $request->fresh()->actions()->latest('id')->firstOrFail();

        try {
            $action->update(['comment' => 'تغییر غیرمجاز']);
            $this->fail('Audit update unexpectedly succeeded.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }

        $this->expectException(LogicException::class);
        $action->delete();
    }

    public function test_returned_request_uses_resubmit_transition_and_audit_action(): void
    {
        [$request, $exec, $director] = $this->createAndSubmitTechnologyRequest('نیازمند اصلاح');
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/return", ['reason' => 'لطفاً هدف جلسه واضح‌تر شود'])->assertOk();
        $this->assertSame(MeetingRequestStatus::RETURNED_FOR_REVISION->value, $request->fresh()->status);
        $this->actingAs($exec, 'sanctum')->putJson("/api/meeting-requests/{$request->id}", ['subject' => 'هدف اصلاح‌شده', 'purpose' => 'COORDINATION', 'priority' => 'NORMAL'])->assertOk();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk()->assertJsonPath('data.status', MeetingRequestStatus::PENDING_INTERNAL_APPROVAL->value);
        $this->assertDatabaseHas('meeting_request_actions', ['meeting_request_id' => $request->id, 'action' => 'RESUBMITTED']);
    }

    private function createAndSubmitTechnologyRequest(string $subject = 'درخواست آزمایشی'): array
    {
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $director = User::where('email', 'tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $employee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id' => $tech->id, 'subject' => $subject, 'purpose' => 'COORDINATION', 'priority' => 'NORMAL', 'participant_ids' => [$employee->id],
        ])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();
        return [$request->fresh(), $exec, $director];
    }
}
