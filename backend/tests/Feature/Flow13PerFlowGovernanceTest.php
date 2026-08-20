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

class Flow13PerFlowGovernanceTest extends TestCase
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
            'FLOW-01' => ['INTERNAL_DIRECTORATE', 'MEETING_COORDINATOR', 1, false, true],
            'FLOW-02' => ['INTER_DIRECTORATE', 'MEETING_COORDINATOR', 2, false, true],
            'FLOW-03' => ['MULTI_DIRECTORATE', 'MEETING_COORDINATOR', 3, false, true],
            'FLOW-04' => ['DIRECTORATE_WITH_HIGH_AUTHORITY', 'EXECUTIVE_SECRETARY', 1, true, true],
            'FLOW-05' => ['MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', 'EXECUTIVE_SECRETARY', 3, true, true],
            'FLOW-06' => ['HIGH_AUTHORITY_INITIATED', 'EXECUTIVE_SECRETARY', 3, true, false],
        ];
    }

    private function meetingForFlow(string $flowType): Meeting
    {
        $tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
        $fin = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
        $hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
        $authorityOffice = OrganizationalUnit::where('code', 'AUTH-OFFICE')->firstOrFail();
        $owner = User::where('email', 'tech.coordinator@example.test')->firstOrFail();
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
