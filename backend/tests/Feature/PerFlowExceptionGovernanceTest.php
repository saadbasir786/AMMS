<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\OrganizationAuthority;
use App\Models\Position;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PerFlowExceptionGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    #[DataProvider('flowGovernanceCases')]
    public function test_each_frozen_flow_has_independent_exception_owner_and_scope_contract(string $flowType, string $ownerRole, int $requiredUnitCount, bool $authorityControlled, bool $requesterIncluded): void
    {
        $meeting = $this->meetingForFlow($flowType);
        $resolved = app(MeetingExceptionGovernanceResolver::class)->resolve($meeting);
        $this->assertSame($ownerRole, $resolved['owner_role']);
        $this->assertSame($authorityControlled, $resolved['authority_controlled']);
        $this->assertCount($requiredUnitCount, $resolved['required_units']);
        $requiredIds = collect($resolved['required_unit_ids']);
        $requesterId = (int) MeetingUnit::where('meeting_id', $meeting->id)->where('role', 'REQUESTING')->value('organizational_unit_id');
        $this->assertSame($requesterIncluded, $requesterId > 0 && $requiredIds->contains($requesterId));
        $requirements = app(MeetingExceptionGovernanceResolver::class)->reviewRequirements($meeting, 'POSTPONEMENT');
        $this->assertCount($requiredUnitCount + ($authorityControlled ? 1 : 0), $requirements);
        if ($authorityControlled) $this->assertSame('HIGH_AUTHORITY', $requirements[array_key_last($requirements)]['reviewer_type']);
    }

    public static function flowGovernanceCases(): array
    {
        return [
            'FLOW-01' => ['INTERNAL_DIRECTORATE', 'EXECUTIVE_MANAGEMENT', 1, false, true],
            'FLOW-02' => ['INTER_DIRECTORATE', 'EXECUTIVE_MANAGEMENT', 2, false, true],
            'FLOW-03' => ['MULTI_DIRECTORATE', 'EXECUTIVE_MANAGEMENT', 3, false, true],
            'FLOW-04' => ['DIRECTORATE_WITH_HIGH_AUTHORITY', 'EXECUTIVE_SECRETARY', 1, true, true],
            'FLOW-05' => ['MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', 'EXECUTIVE_SECRETARY', 3, true, true],
            'FLOW-06' => ['HIGH_AUTHORITY_INITIATED', 'EXECUTIVE_SECRETARY', 3, true, false],
        ];
    }

    public function test_flow01_owner_and_director_complete_postponement(): void
    {
        $meeting = $this->meetingForFlow('INTERNAL_DIRECTORATE');
        $owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $director = User::where('email', 'tech.director@example.test')->firstOrFail();
        $case = $this->createAndSubmit($meeting, $owner);
        $review = $this->unitReview($case, OrganizationalUnit::where('code', 'TECH')->value('id'));
        $this->actingAs($director, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$review->id.'/approve')->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$case->id.'/apply')->assertOk();
        $this->assertSame('POSTPONED', $meeting->fresh()->status);
    }

    public function test_flow02_requires_both_directorate_approvals_and_denies_cross_unit_substitution(): void
    {
        $meeting = $this->meetingForFlow('INTER_DIRECTORATE');
        $owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = User::where('email', 'tech.director@example.test')->firstOrFail();
        $finance = User::where('email', 'finance.director@example.test')->firstOrFail();
        $case = $this->createAndSubmit($meeting, $owner);
        $techReview = $this->unitReview($case, OrganizationalUnit::where('code', 'TECH')->value('id'));
        $financeReview = $this->unitReview($case, OrganizationalUnit::where('code', 'FIN')->value('id'));
        $this->actingAs($tech, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$techReview->id.'/approve')->assertOk();
        $this->actingAs($tech, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$financeReview->id.'/approve')->assertStatus(403);
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$case->id.'/apply')->assertStatus(409);
        $this->actingAs($finance, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$financeReview->id.'/approve')->assertOk();
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$case->id.'/apply')->assertOk();
        $this->assertSame('POSTPONED', $meeting->fresh()->status);
    }

    public function test_flow03_correction_creates_v2_and_v1_approvals_do_not_transfer(): void
    {
        $meeting = $this->meetingForFlow('MULTI_DIRECTORATE');
        $owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $tech = User::where('email', 'tech.director@example.test')->firstOrFail();
        $finance = User::where('email', 'finance.director@example.test')->firstOrFail();
        $hr = User::where('email', 'hr.director@example.test')->firstOrFail();
        $case = $this->createAndSubmit($meeting, $owner);
        $techReview = $this->unitReview($case, OrganizationalUnit::where('code', 'TECH')->value('id'));
        $financeReview = $this->unitReview($case, OrganizationalUnit::where('code', 'FIN')->value('id'));
        $hrReview = $this->unitReview($case, OrganizationalUnit::where('code', 'HR')->value('id'));
        $this->actingAs($tech, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$techReview->id.'/approve')->assertOk();
        $this->actingAs($finance, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$financeReview->id.'/approve')->assertOk();
        $this->actingAs($hr, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$hrReview->id.'/correction', ['reason' => 'اصلاح نسخهٔ اول لازم است.'])->assertOk();
        $oldVersionId = $case->current_version_id;
        $this->actingAs($owner, 'sanctum')->putJson('/api/meeting-exceptions/'.$case->id.'/draft', ['reason' => 'نسخهٔ دوم اصلاح‌شده.'])->assertOk();
        $case = $case->fresh(['currentVersion.reviews']);
        $this->assertNotSame($oldVersionId, $case->current_version_id);
        $this->assertCount(0, $case->currentVersion->reviews);
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$case->id.'/submit')->assertOk();
        $case = $case->fresh(['currentVersion.reviews']);
        $this->assertCount(3, $case->currentVersion->reviews);
        $this->assertTrue($case->currentVersion->reviews->every(fn ($review) => $review->status === 'PENDING'));
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$case->id.'/apply')->assertStatus(409);
        foreach ([[$techReview->organizational_unit_id, $tech], [$financeReview->organizational_unit_id, $finance], [$hrReview->organizational_unit_id, $hr]] as [$unitId, $actor]) {
            $review = $this->unitReview($case, $unitId);
            $this->actingAs($actor, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$review->id.'/approve')->assertOk();
        }
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$case->id.'/apply')->assertOk();
        $this->assertSame('POSTPONED', $meeting->fresh()->status);
    }

    public function test_flow04_requires_requester_ack_exact_high_authority_and_executive_secretary_apply(): void
    {
        $this->completeAuthorityFlow('DIRECTORATE_WITH_HIGH_AUTHORITY', true, true);
    }

    public function test_flow05_requires_all_formal_acks_exact_high_authority_and_no_authority_office_substitution(): void
    {
        $meeting = $this->meetingForFlow('MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY');
        $secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
        $high = User::where('email', 'high.authority@example.test')->firstOrFail();
        $case = $this->createAndSubmit($meeting, $secretary);
        foreach (['TECH' => 'tech.director@example.test', 'FIN' => 'finance.director@example.test', 'HR' => 'hr.director@example.test'] as $code => $email) {
            $review = $this->unitReview($case, OrganizationalUnit::where('code', $code)->value('id'));
            $this->actingAs(User::where('email', $email)->firstOrFail(), 'sanctum')->postJson('/api/meeting-exception-reviews/'.$review->id.'/acknowledge')->assertOk();
        }
        $highReview = $this->highReview($case);
        $this->actingAs(User::where('email', 'authority.office@example.test')->firstOrFail(), 'sanctum')->postJson('/api/meeting-exception-reviews/'.$highReview->id.'/approve')->assertStatus(403);
        $this->actingAs($high, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$highReview->id.'/approve')->assertOk();
        $this->actingAs($secretary, 'sanctum')->postJson('/api/meeting-exceptions/'.$case->id.'/apply')->assertOk();
        $this->assertSame('POSTPONED', $meeting->fresh()->status);
    }

    public function test_flow06_has_no_requester_review_and_exact_high_authority_apply(): void
    {
        $meeting = $this->meetingForFlow('HIGH_AUTHORITY_INITIATED');
        $secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
        $high = User::where('email', 'high.authority@example.test')->firstOrFail();
        $case = $this->createAndSubmit($meeting, $secretary);
        $case = $case->fresh(['currentVersion.reviews']);
        $this->assertNull($meeting->request->requesting_unit_id);
        $this->assertCount(4, $case->currentVersion->reviews);
        $this->assertCount(3, $case->currentVersion->reviews->where('reviewer_type', 'UNIT'));
        $this->assertCount(1, $case->currentVersion->reviews->where('reviewer_type', 'HIGH_AUTHORITY'));
        $this->assertTrue($case->currentVersion->reviews->where('reviewer_type', 'UNIT')->every(fn ($review) => $review->review_requirement === 'ACKNOWLEDGEMENT'));
        foreach (['TECH' => 'tech.director@example.test', 'FIN' => 'finance.director@example.test', 'HR' => 'hr.director@example.test'] as $code => $email) {
            $review = $this->unitReview($case, OrganizationalUnit::where('code', $code)->value('id'));
            $this->actingAs(User::where('email', $email)->firstOrFail(), 'sanctum')->postJson('/api/meeting-exception-reviews/'.$review->id.'/acknowledge')->assertOk();
        }
        $this->actingAs($high, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$this->highReview($case)->id.'/approve')->assertOk();
        $this->actingAs($secretary, 'sanctum')->postJson('/api/meeting-exceptions/'.$case->id.'/apply')->assertOk();
        $this->assertSame('POSTPONED', $meeting->fresh()->status);
    }

    private function completeAuthorityFlow(string $flowType, bool $assertRequesterAck, bool $assertHighAuthority): void
    {
        $meeting = $this->meetingForFlow($flowType);
        $secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
        $high = User::where('email', 'high.authority@example.test')->firstOrFail();
        $case = $this->createAndSubmit($meeting, $secretary);
        $unitReview = $this->unitReview($case, OrganizationalUnit::where('code', 'TECH')->value('id'));
        if ($assertRequesterAck) $this->actingAs(User::where('email', 'tech.director@example.test')->firstOrFail(), 'sanctum')->postJson('/api/meeting-exception-reviews/'.$unitReview->id.'/acknowledge')->assertOk();
        if ($assertHighAuthority) $this->actingAs($high, 'sanctum')->postJson('/api/meeting-exception-reviews/'.$this->highReview($case)->id.'/approve')->assertOk();
        $this->actingAs($secretary, 'sanctum')->postJson('/api/meeting-exceptions/'.$case->id.'/apply')->assertOk();
        $this->assertSame('POSTPONED', $meeting->fresh()->status);
    }

    private function createAndSubmit(Meeting $meeting, User $owner): \App\Models\MeetingExceptionCase
    {
        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/meetings/'.$meeting->id.'/exceptions', ['case_type' => 'POSTPONEMENT', 'reason' => 'FLOW-13 per-flow behavioral test.'])->assertCreated()->json('data');
        $this->actingAs($owner, 'sanctum')->postJson('/api/meeting-exceptions/'.$created['id'].'/submit')->assertOk();
        return \App\Models\MeetingExceptionCase::with('currentVersion.reviews')->findOrFail($created['id']);
    }

    private function unitReview(\App\Models\MeetingExceptionCase $case, int $unitId): \App\Models\MeetingExceptionReview
    {
        return $case->fresh(['currentVersion.reviews'])->currentVersion->reviews->firstOrFail(fn ($review) => (int) $review->organizational_unit_id === $unitId);
    }

    private function highReview(\App\Models\MeetingExceptionCase $case): \App\Models\MeetingExceptionReview
    {
        return $case->fresh(['currentVersion.reviews'])->currentVersion->reviews->firstOrFail(fn ($review) => $review->reviewer_type === 'HIGH_AUTHORITY');
    }

    private function meetingForFlow(string $flowType): Meeting
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $fin = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
        $authorityOffice = OrganizationalUnit::where('code', 'AUTH-OFFICE')->firstOrFail();
        $owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
        $chairperson = User::where('email', 'tech.director@example.test')->firstOrFail();
        $request = MeetingRequest::create(['request_number' => 'FLOW13-GOV-'.uniqid(), 'flow_type' => $flowType, 'requesting_unit_id' => $flowType === 'HIGH_AUTHORITY_INITIATED' ? null : $tech->id, 'created_by' => $owner->id, 'subject' => 'FLOW-13 governance fixture', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
        $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW13-GOV-'.uniqid(), 'title' => 'FLOW-13 governance fixture', 'organizing_unit_id' => $flowType === 'HIGH_AUTHORITY_INITIATED' ? $authorityOffice->id : $tech->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $chairperson->employee_id, 'meeting_date' => now()->addDays(10)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW-13 GOV', 'status' => 'SCHEDULED']);
        $roles = match ($flowType) {
            'INTERNAL_DIRECTORATE' => [[$tech->id, 'REQUESTING']],
            'INTER_DIRECTORATE' => [[$tech->id, 'REQUESTING'], [$fin->id, 'PARTICIPATING']],
            'MULTI_DIRECTORATE' => [[$tech->id, 'REQUESTING'], [$fin->id, 'PARTICIPATING'], [$hr->id, 'PARTICIPATING']],
            'DIRECTORATE_WITH_HIGH_AUTHORITY' => [[$tech->id, 'REQUESTING']],
            'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' => [[$tech->id, 'REQUESTING'], [$fin->id, 'PARTICIPATING'], [$hr->id, 'PARTICIPATING']],
            default => [[$tech->id, 'PARTICIPATING'], [$fin->id, 'PARTICIPATING'], [$hr->id, 'PARTICIPATING']],
        };
        foreach ($roles as [$unitId, $role]) MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unitId, 'role' => $role]);
        if (str_contains($flowType, 'HIGH_AUTHORITY') || $flowType === 'DIRECTORATE_WITH_HIGH_AUTHORITY') {
            $high = User::where('email', 'high.authority@example.test')->firstOrFail();
            MeetingAuthorityContext::create(['meeting_id' => $meeting->id, 'organization_authority_id' => OrganizationAuthority::firstOrFail()->id, 'authority_type' => 'HIGH_AUTHORITY', 'authority_position_id' => Position::firstOrFail()->id, 'authority_employee_id' => $high->employee_id, 'authority_office_unit_id' => $authorityOffice->id]);
        }
        return $meeting->fresh(['request', 'units', 'authorityContext']);
    }
}
