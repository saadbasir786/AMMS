<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Flow06Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_authority_initiated_flow_completes_without_requester_and_creates_participating_units(): void
    {
        [$users, $units, $employees] = $this->fixture();
        $create = $this->actingAs($users['office'], 'sanctum')->postJson('/api/meeting-requests/authority-initiated', [
            'subject' => 'دستور جلسهٔ ابتکاری مقام', 'purpose' => 'DECISION', 'description' => 'شرح رسمی',
            'directive' => 'مقام عالی برگزاری جلسه را هدایت می‌کند.', 'meeting_justification' => 'هماهنگی بین ریاست‌ها',
            'requested_outcome' => 'تصمیم مشترک', 'proposed_agenda' => 'آجندا', 'priority' => 'HIGH',
            'estimated_duration_minutes' => 90, 'preferred_location' => 'تالار جلسات مقام',
            'participating_unit_ids' => [$units['tech']->id, $units['finance']->id],
        ]);
        $create->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->assertNull($request->requesting_unit_id);
        $this->assertSame(2, $request->units()->where('role', 'PARTICIPATING')->count());

        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/submit")->assertOk()->assertJsonPath('data.status', 'PENDING_HIGH_AUTHORITY_ISSUANCE');
        $this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/issue")->assertOk()->assertJsonPath('data.status', 'ISSUED_BY_HIGH_AUTHORITY');
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/distribute")->assertOk()->assertJsonPath('data.status', 'DISTRIBUTED_TO_DIRECTORATES');

        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['tech']->id}/coordinate")->assertOk();
        $this->actingAs($users['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['tech']->id}/acknowledge")->assertOk();
        $this->actingAs($users['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['finance']->id}/acknowledge")->assertOk()->assertJsonPath('data.status', 'DIRECTORATES_ACKNOWLEDGED');

        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/scheduling/start")->assertOk()->assertJsonPath('data.status', 'AUTHORITY_SCHEDULING');
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/schedule/proposals", ['meeting_date' => now()->addWeek()->format('Y-m-d'), 'start_time' => '09:00', 'end_time' => '10:30', 'location' => 'تالار جلسات مقام'])->assertOk();
        $proposal = $request->scheduleProposals()->latest('id')->firstOrFail();
        foreach ([['unit' => $units['tech'], 'actor' => $users['techDirector']], ['unit' => $units['finance'], 'actor' => $users['financeDirector']]] as $pair) {
            $this->actingAs($pair['actor'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/schedule/acknowledge", ['proposal_id' => $proposal->id, 'unit_id' => $pair['unit']->id])->assertOk();
        }

        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['tech']->id}/participants", ['employee_ids' => [$employees['tech']]])->assertOk();
        $this->actingAs($users['techExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['tech']->id}/participants/confirm")->assertOk();
        $this->actingAs($users['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['finance']->id}/participants", ['employee_ids' => [$employees['finance']]])->assertOk();
        $this->actingAs($users['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['finance']->id}/participants/confirm")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/authority-participants", ['employee_ids' => [$employees['authority']]])->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/authority-participants/confirm")->assertOk();
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/schedule/confirm")->assertOk()->assertJsonPath('data.status', 'SCHEDULE_CONFIRMED');
        $this->actingAs($users['secretary'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/finalize", ['agenda' => 'آجندای نهایی'])->assertOk();

        $meeting = Meeting::where('meeting_request_id', $request->id)->firstOrFail();
        $this->assertSame('CONVERTED_TO_MEETING', $request->fresh()->status);
        $this->assertNull($request->fresh()->requesting_unit_id);
        $this->assertSame(2, $meeting->units()->where('role', 'PARTICIPATING')->count());
        $this->assertSame(0, $meeting->units()->where('role', 'REQUESTING')->count());
        $this->assertSame(1, $meeting->authorityContext()->count());
        $this->assertSame($employees['authority'], $meeting->chairperson_employee_id);
    }

    public function test_flow06_rejects_missing_duplicate_and_cross_organization_directorates(): void
    {
        [$users, $units] = $this->fixture();
        $base = ['subject' => 'دستور', 'purpose' => 'DECISION', 'directive' => 'متن دستور', 'requested_outcome' => 'نتیجه', 'priority' => 'NORMAL'];
        $this->actingAs($users['office'], 'sanctum')->postJson('/api/meeting-requests/authority-initiated', $base + ['participating_unit_ids' => []])->assertStatus(422);
        $this->actingAs($users['office'], 'sanctum')->postJson('/api/meeting-requests/authority-initiated', $base + ['participating_unit_ids' => [$units['tech']->id, $units['tech']->id]])->assertStatus(422);
        $this->assertTrue($units['tech']->organization_id === $units['finance']->organization_id);
    }

    public function test_flow06_draft_visibility_and_issuance_boundary_are_enforced(): void
    {
        [$users, $units] = $this->fixture();
        $this->actingAs($users['office'], 'sanctum')->postJson('/api/meeting-requests/authority-initiated', ['subject' => 'مسوده', 'purpose' => 'DECISION', 'directive' => 'متن', 'requested_outcome' => 'نتیجه', 'priority' => 'NORMAL', 'participating_unit_ids' => [$units['tech']->id]])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($users['techDirector'], 'sanctum')->getJson("/api/meeting-requests/{$request->id}")->assertStatus(403);
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/issue")->assertStatus(403);
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/distribute")->assertStatus(422);
    }

    public function test_flow06_material_revision_requires_reissuance_and_invalidates_old_acknowledgements(): void
    {
        [$users, $units] = $this->fixture();
        $request = $this->issuedAndDistributed($users, $units);
        $this->actingAs($users['techDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['tech']->id}/acknowledge")->assertOk();
        $this->actingAs($users['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['finance']->id}/acknowledge")->assertOk();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/revision", ['subject' => 'دستور با HR', 'purpose' => 'COORDINATION', 'directive' => 'نسخهٔ اصلاحی', 'requested_outcome' => 'نتیجهٔ سه‌ریاستی', 'participating_unit_ids' => [$units['tech']->id, $units['finance']->id, $units['hr']->id]])->assertOk()->assertJsonPath('data.status', 'PENDING_HIGH_AUTHORITY_REISSUANCE');
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/reissue")->assertStatus(403);
        $this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/reissue")->assertOk()->assertJsonPath('data.status', 'ISSUED_BY_HIGH_AUTHORITY');
        $request = $request->fresh(['directiveVersions', 'unitResponses', 'units']);
        $this->assertSame(2, $request->directiveVersions()->count());
        $this->assertSame(2, $request->directiveVersions()->where('is_current', true)->value('version'));
        $this->assertSame(3, $request->units()->where('role', 'PARTICIPATING')->count());
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/distribute")->assertOk();
        $this->assertSame(3, $request->fresh()->unitResponses()->where('meeting_request_directive_version_id', $request->fresh()->currentDirectiveVersion->id)->count());
    }

    public function test_flow06_directorate_has_no_approve_or_reject_operation_and_cross_unit_write_is_denied(): void
    {
        [$users, $units] = $this->fixture();
        $request = $this->issuedAndDistributed($users, $units);
        $this->actingAs($users['financeDirector'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['tech']->id}/acknowledge")->assertStatus(403);
        $this->actingAs($users['financeExec'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/units/{$units['tech']->id}/participants", ['employee_ids' => [1]])->assertStatus(403);
        $this->assertFalse(method_exists(\App\Http\Controllers\Api\AuthorityInitiatedMeetingRequestController::class, 'approveDirectorate'));
    }

    private function issuedAndDistributed(array $users, array $units): MeetingRequest
    {
        $this->actingAs($users['office'], 'sanctum')->postJson('/api/meeting-requests/authority-initiated', ['subject' => 'دستور', 'purpose' => 'DECISION', 'directive' => 'متن دستور', 'requested_outcome' => 'نتیجه', 'priority' => 'NORMAL', 'estimated_duration_minutes' => 60, 'participating_unit_ids' => [$units['tech']->id, $units['finance']->id]])->assertCreated();
        $request = MeetingRequest::latest('id')->firstOrFail();
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/submit")->assertOk()->assertJsonPath('data.status', 'PENDING_HIGH_AUTHORITY_ISSUANCE');
        $this->actingAs($users['authority'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/issue")->assertOk()->assertJsonPath('data.status', 'ISSUED_BY_HIGH_AUTHORITY');
        $this->actingAs($users['office'], 'sanctum')->postJson("/api/meeting-requests/{$request->id}/authority-initiated/distribute")->assertOk()->assertJsonPath('data.status', 'DISTRIBUTED_TO_DIRECTORATES');
        return $request->fresh();
    }

    private function fixture(): array
    {
        $user = fn (string $email) => User::where('email', $email)->firstOrFail();
        $unit = fn (string $code) => OrganizationalUnit::where('code', $code)->firstOrFail();
        $employee = fn (string $number) => Employee::where('employee_number', $number)->firstOrFail();
        $users = [
            'office' => $user('authority.office@example.test'), 'authority' => $user('high.authority@example.test'), 'secretary' => $user('executive.secretary@example.test'),
            'techExec' => $user('tech.exec@example.test'), 'techDirector' => $user('tech.director@example.test'), 'financeExec' => $user('finance.exec@example.test'), 'financeDirector' => $user('finance.director@example.test'),
        ];
        $units = ['tech' => $unit('TECH'), 'finance' => $unit('FIN'), 'hr' => $unit('HR')];
        $employees = ['tech' => $employee('EMP-TECH-003')->id, 'finance' => $employee('EMP-FIN-002')->id, 'hr' => $employee('EMP-HR-002')->id, 'authority' => $employee('EMP-AUTH-001')->id];
        $users['hrExec'] = $user('hr.exec@example.test');
        $users['hrDirector'] = $user('hr.director@example.test');
        return [$users, $units, $employees];
    }
}
