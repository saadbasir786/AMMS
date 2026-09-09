<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateHighAuthorityMeetingRequest
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $data): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.create'), 403, 'FORBIDDEN');

            $requester = OrganizationalUnit::with('organization')->findOrFail((int) $data['requesting_unit_id']);
            abort_unless($requester->isFormalGovernanceUnit() && $requester->status === 'ACTIVE', 422, 'INVALID_REQUESTING_DIRECTORATE');
            abort_unless($this->canManageRequester($actor, $requester->id), 403, 'FORBIDDEN');
            $authority = $this->activeAuthorityFor((new MeetingRequest(['requesting_unit_id' => $requester->id]))->setRelation('requestingUnit', $requester));

            $this->assertRequesterParticipants((new MeetingRequest())->setRelation('requestingUnit', $requester), $data['participant_ids'] ?? []);
            $this->ensureRequesterApproverExists($requester->id);

            $request = MeetingRequest::create([
                'flow_type' => 'DIRECTORATE_WITH_HIGH_AUTHORITY',
                'requesting_unit_id' => $requester->id,
                'created_by' => $actor->id,
                'subject' => $data['subject'],
                'purpose' => $data['purpose'],
                'description' => $data['description'] ?? null,
                'meeting_justification' => $data['meeting_justification'],
                'requested_outcome' => $data['requested_outcome'],
                'priority' => $data['priority'],
                'preferred_date' => $data['preferred_date'] ?? null,
                'preferred_start_time' => $data['preferred_start_time'] ?? null,
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? null,
                'proposed_location' => $data['proposed_location'] ?? null,
                'status' => MeetingRequestStatus::DRAFT->value,
                'lead_coordinator_unit_id' => $requester->id,
                'lead_coordinator_user_id' => $actor->id,
            ]);

            $request->authorityContext()->create([
                'organization_authority_id' => $authority->id,
                'authority_type' => $authority->authority_type,
                'authority_office_unit_id' => $authority->authority_office_unit_id,
                'authority_position_id' => $authority->authority_position_id,
                'office_review_status' => 'PENDING',
                'authority_decision_status' => 'PENDING',
            ]);
            $request->units()->create(['organizational_unit_id' => $requester->id, 'role' => 'REQUESTER']);

            foreach (array_values(array_unique(array_map('intval', $data['participant_ids'] ?? []))) as $employeeId) {
                $employee = Employee::findOrFail($employeeId);
                $request->participants()->create(['employee_id' => $employeeId, 'participant_type' => 'PROPOSED', 'created_by' => $actor->id]);
                $request->nominations()->create([
                    'employee_id' => $employeeId,
                    'organizational_unit_id' => $employee->organizational_unit_id,
                    'nominated_by_unit_id' => $requester->id,
                    'nominated_by_user_id' => $actor->id,
                    'participant_stage' => 'PROPOSED',
                    'nomination_status' => 'NOMINATED',
                ]);
            }

            $this->record($request, $actor, 'CREATED', null, MeetingRequestStatus::DRAFT->value, null, ['flow_type' => 'DIRECTORATE_WITH_HIGH_AUTHORITY', 'authority_type' => $authority->authority_type]);
            return $this->responseRequest($request);
        });
    }

    private function ensureRequesterApproverExists(int $unitId): void
    {
        abort_unless(User::whereHas('roleScopes', fn ($q) => $q->where('organizational_unit_id', $unitId)->whereHas('role', fn ($role) => $role->where('name', 'DIRECTOR')->where('guard_name', 'sanctum')))->exists(), 422, 'UNAUTHORIZED_APPROVER');
    }
}
