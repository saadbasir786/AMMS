<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateMultiHighAuthorityMeetingRequest
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $data): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.create'), 403, 'FORBIDDEN');
            $requester = OrganizationalUnit::with('organization')->findOrFail((int) $data['requesting_unit_id']);
            $participantIds = array_values(array_unique(array_map('intval', $data['participating_unit_ids'] ?? [])));
            $units = collect([['organizational_unit_id' => $requester->id, 'role' => 'REQUESTER']])->concat(collect($participantIds)->map(fn ($id) => ['organizational_unit_id' => $id, 'role' => 'PARTICIPATING']));
            $unitObjects = OrganizationalUnit::whereIn('id', $units->pluck('organizational_unit_id'))->get()->keyBy('id');
            abort_unless($unitObjects->count() === $units->count(), 422, 'INVALID_PARTICIPATING_DIRECTORATE');
            $rows = $units->map(fn (array $row) => (object) ['organizational_unit_id' => $row['organizational_unit_id'], 'role' => $row['role'], 'unit' => $unitObjects->get($row['organizational_unit_id'])]);
            $this->assertUnitSet($rows);
            abort_unless($this->canManageUnit($actor, $requester->id), 403, 'FORBIDDEN');
            $this->activeAuthorityFor((new MeetingRequest())->setRelation('units', collect([(object) ['role' => 'REQUESTER', 'unit' => $requester]])));
            $this->assertEmployeesInUnit($data['participant_ids'] ?? [], $requester);
            foreach ($unitObjects as $unit) abort_unless(User::whereHas('roleScopes', fn ($q) => $q->where('organizational_unit_id', $unit->id)->whereHas('role', fn ($r) => $r->where('name', 'DIRECTOR')->where('guard_name', 'sanctum')))->exists(), 422, 'UNAUTHORIZED_APPROVER');

            $request = MeetingRequest::create([
                'flow_type' => 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', 'requesting_unit_id' => $requester->id, 'created_by' => $actor->id,
                'subject' => $data['subject'], 'purpose' => $data['purpose'], 'description' => $data['description'] ?? null,
                'meeting_justification' => $data['meeting_justification'] ?? null, 'requested_outcome' => $data['requested_outcome'] ?? null,
                'priority' => $data['priority'], 'preferred_date' => $data['preferred_date'] ?? null, 'preferred_start_time' => $data['preferred_start_time'] ?? null,
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? null, 'proposed_location' => $data['proposed_location'] ?? null,
                'status' => MeetingRequestStatus::DRAFT->value, 'lead_coordinator_unit_id' => $requester->id, 'lead_coordinator_user_id' => $actor->id,
            ]);
            $request->units()->createMany($units->all());
            foreach ($participantIds as $unitId) $request->unitReviews()->create(['organizational_unit_id' => $unitId, 'review_status' => 'PENDING_REVIEW']);
            $authority = $this->activeAuthorityFor($request->load('requestingUnit.organization'));
            $request->authorityContext()->create(['organization_authority_id' => $authority->id, 'authority_type' => $authority->authority_type, 'authority_office_unit_id' => $authority->authority_office_unit_id, 'authority_position_id' => $authority->authority_position_id, 'office_review_status' => 'PENDING', 'authority_decision_status' => 'PENDING']);
            foreach (array_values(array_unique(array_map('intval', $data['participant_ids'] ?? []))) as $employeeId) {
                $employee = Employee::findOrFail($employeeId);
                $request->participants()->create(['employee_id' => $employeeId, 'participant_type' => 'PROPOSED', 'created_by' => $actor->id]);
                $request->nominations()->create(['employee_id' => $employeeId, 'organizational_unit_id' => $requester->id, 'nominated_by_unit_id' => $requester->id, 'nominated_by_user_id' => $actor->id, 'participant_stage' => 'PROPOSED', 'nomination_status' => 'NOMINATED']);
            }
            $this->record($request, $actor, 'CREATED', null, MeetingRequestStatus::DRAFT->value, null, ['flow_type' => 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', 'participating_unit_ids' => $participantIds, 'authority_type' => $authority->authority_type]);
            return $this->responseRequest($request);
        });
    }
}
