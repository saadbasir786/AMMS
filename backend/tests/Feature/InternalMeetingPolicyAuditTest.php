<?php

namespace Tests\Feature;

use App\Models\AmmsRoleScope;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalMeetingPolicyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_returned_flow01_can_be_edited_and_resubmitted_by_current_scoped_creator(): void
    {
        [$exec, $director, $tech, $employees] = $this->actors();

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id' => $tech->id,
            'subject' => 'درخواست برای اصلاح',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'participant_ids' => $employees,
        ])->assertCreated();

        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();
        $this->actingAs($director, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/return", ['reason' => 'موضوع و زمان پیشنهادی دقیق شود.'])->assertOk();

        $this->actingAs($exec, 'sanctum')->putJson("/api/meeting-requests/{$request->id}", [
            'subject' => 'درخواست اصلاح‌شده',
            'preferred_date' => now()->addWeek()->format('Y-m-d'),
            'preferred_start_time' => '08:45',
            'estimated_duration_minutes' => 75,
            'proposed_location' => 'دفتر ریاست تکنالوژی',
            'participant_ids' => $employees,
        ])->assertOk();

        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertOk();

        $request->refresh();
        $this->assertSame('PENDING_INTERNAL_APPROVAL', $request->status);
        $this->assertSame('درخواست اصلاح‌شده', $request->subject);
        $this->assertDatabaseHas('meeting_request_actions', [
            'meeting_request_id' => $request->id,
            'action' => 'RESUBMITTED',
        ]);
    }

    public function test_creator_loses_edit_submit_schedule_and_finalize_authority_when_exec_scope_is_revoked(): void
    {
        [$exec, $director, $tech, $employees] = $this->actors();

        $this->actingAs($exec, 'sanctum')->postJson('/api/meeting-requests', [
            'requesting_unit_id' => $tech->id,
            'subject' => 'آزمون سلب صلاحیت',
            'purpose' => 'COORDINATION',
            'priority' => 'NORMAL',
            'participant_ids' => $employees,
        ])->assertCreated();

        $request = MeetingRequest::latest('id')->firstOrFail();

        AmmsRoleScope::query()
            ->where('user_id', $exec->id)
            ->where('organizational_unit_id', $tech->id)
            ->whereHas('role', fn ($q) => $q->where('name', 'EXECUTIVE_MANAGEMENT'))
            ->delete();

        $this->actingAs($exec, 'sanctum')->putJson("/api/meeting-requests/{$request->id}", ['subject' => 'نباید تغییر کند'])->assertForbidden();
        $this->actingAs($exec, 'sanctum')->postJson("/api/meeting-requests/{$request->id}/submit")->assertForbidden();

        $this->assertSame('DRAFT', $request->fresh()->status);
    }

    private function actors(): array
    {
        $exec = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $director = User::where('email', 'tech.director@example.test')->firstOrFail();
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $employees = Employee::whereIn('employee_number', ['EMP-TECH-001', 'EMP-TECH-003'])->pluck('id')->all();
        return [$exec, $director, $tech, $employees];
    }
}
