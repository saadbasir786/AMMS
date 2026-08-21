<?php

namespace Tests\Feature;

use App\Models\MeetingRequest;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorityRoleSeparationPolicyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_flow04_schedule_and_authority_participant_ownership_are_separated(): void
    {
        $request = $this->authorityRequest('DIRECTORATE_WITH_HIGH_AUTHORITY');
        $office = $this->user('authority.office@example.test');
        $secretary = $this->user('executive.secretary@example.test');
        $system = $this->user('system.admin@example.test');

        $this->assertFalse(Gate::forUser($office)->allows('flow04Scheduler', $request));
        $this->assertTrue(Gate::forUser($secretary)->allows('flow04Scheduler', $request));
        $this->assertFalse(Gate::forUser($system)->allows('flow04Scheduler', $request));

        $this->assertTrue(Gate::forUser($office)->allows('flow04AuthorityParticipants', $request));
        $this->assertFalse(Gate::forUser($secretary)->allows('flow04AuthorityParticipants', $request));
        $this->assertFalse(Gate::forUser($system)->allows('flow04AuthorityParticipants', $request));
    }

    public function test_flow05_schedule_participant_ownership_and_directorate_confirmation_are_separated(): void
    {
        $request = $this->authorityRequest('MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY');
        $tech = $this->unit('TECH');
        $finance = $this->unit('FIN');
        $request->units()->createMany([
            ['organizational_unit_id' => $tech->id, 'role' => 'REQUESTER'],
            ['organizational_unit_id' => $finance->id, 'role' => 'PARTICIPATING'],
        ]);

        $office = $this->user('authority.office@example.test');
        $secretary = $this->user('executive.secretary@example.test');
        $system = $this->user('system.admin@example.test');
        $financeExec = $this->user('finance.exec@example.test');
        $financeDirector = $this->user('finance.director@example.test');

        $this->assertFalse(Gate::forUser($office)->allows('flow05Schedule', $request));
        $this->assertTrue(Gate::forUser($secretary)->allows('flow05Schedule', $request));
        $this->assertFalse(Gate::forUser($system)->allows('flow05Schedule', $request));

        $this->assertTrue(Gate::forUser($office)->allows('flow05AuthorityParticipants', $request));
        $this->assertFalse(Gate::forUser($secretary)->allows('flow05AuthorityParticipants', $request));

        $this->assertTrue(Gate::forUser($financeExec)->allows('flow05UnitParticipantsManage', [$request, $finance->id]));
        $this->assertFalse(Gate::forUser($financeExec)->allows('flow05UnitParticipantsConfirm', [$request, $finance->id]));
        $this->assertFalse(Gate::forUser($financeDirector)->allows('flow05UnitParticipantsManage', [$request, $finance->id]));
        $this->assertTrue(Gate::forUser($financeDirector)->allows('flow05UnitParticipantsConfirm', [$request, $finance->id]));
    }

    public function test_flow06_exact_roles_are_required_for_schedule_and_participant_ownership(): void
    {
        $request = $this->authorityRequest('HIGH_AUTHORITY_INITIATED', null);
        $tech = $this->unit('TECH');
        $request->units()->create(['organizational_unit_id' => $tech->id, 'role' => 'PARTICIPATING']);

        $office = $this->user('authority.office@example.test');
        $secretary = $this->user('executive.secretary@example.test');
        $system = $this->user('system.admin@example.test');
        $techExec = $this->user('tech.exec@example.test');
        $techDirector = $this->user('tech.director@example.test');

        $this->assertFalse(Gate::forUser($office)->allows('flow06Schedule', $request));
        $this->assertTrue(Gate::forUser($secretary)->allows('flow06Schedule', $request));
        $this->assertFalse(Gate::forUser($system)->allows('flow06Schedule', $request));

        $this->assertTrue(Gate::forUser($office)->allows('flow06AuthorityParticipants', $request));
        $this->assertFalse(Gate::forUser($secretary)->allows('flow06AuthorityParticipants', $request));

        $this->assertTrue(Gate::forUser($techExec)->allows('flow06UnitParticipantsManage', [$request, $tech->id]));
        $this->assertFalse(Gate::forUser($techExec)->allows('flow06UnitParticipantsConfirm', [$request, $tech->id]));
        $this->assertFalse(Gate::forUser($techDirector)->allows('flow06UnitParticipantsManage', [$request, $tech->id]));
        $this->assertTrue(Gate::forUser($techDirector)->allows('flow06UnitParticipantsConfirm', [$request, $tech->id]));
    }

    private function authorityRequest(string $flowType, ?int $requestingUnitId = -1): MeetingRequest
    {
        $authority = OrganizationAuthority::where('is_primary', true)->firstOrFail();
        $authorityEmployee = $this->user('high.authority@example.test')->employee;
        $requestingUnitId = $requestingUnitId === -1 ? $this->unit('TECH')->id : $requestingUnitId;

        $request = MeetingRequest::create([
            'organization_id' => $authority->organization_id,
            'flow_type' => $flowType,
            'status' => 'DRAFT',
            'requesting_unit_id' => $requestingUnitId,
            'subject' => 'Policy audit',
            'purpose' => 'DECISION',
            'priority' => 'NORMAL',
            'created_by' => $this->user('tech.exec@example.test')->id,
        ]);

        $request->authorityContext()->create([
            'organization_authority_id' => $authority->id,
            'authority_type' => $authority->authority_type,
            'authority_position_id' => $authority->authority_position_id,
            'authority_employee_id' => $authorityEmployee->id,
            'authority_office_unit_id' => $authority->authority_office_unit_id,
        ]);

        return $request->fresh(['authorityContext']);
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
