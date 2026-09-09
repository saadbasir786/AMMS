<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateHighAuthorityMeetingRequest
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $locked = $this->lockHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            abort_unless($locked->created_by === $actor->id && $this->canManageRequester($actor, $locked->requesting_unit_id), 403, 'FORBIDDEN');
            abort_unless(in_array($locked->status, [
                MeetingRequestStatus::DRAFT->value,
                MeetingRequestStatus::RETURNED_TO_REQUESTER->value,
                MeetingRequestStatus::RETURNED_BY_AUTHORITY_OFFICE->value,
                MeetingRequestStatus::RETURNED_BY_HIGH_AUTHORITY->value,
            ], true), 422, 'REQUEST_NOT_EDITABLE');

            if (array_key_exists('flow_type', $data) && $data['flow_type'] !== 'DIRECTORATE_WITH_HIGH_AUTHORITY') {
                abort(422, 'MULTIPLE_DIRECTORATES_REQUIRE_HIGH_AUTHORITY_MULTI_FLOW');
            }

            $oldRequesterId = (int) $locked->requesting_unit_id;
            $newRequesterId = (int) ($data['requesting_unit_id'] ?? $oldRequesterId);
            $material = $newRequesterId !== $oldRequesterId
                || (array_key_exists('subject', $data) && $data['subject'] !== $locked->subject)
                || (array_key_exists('purpose', $data) && $data['purpose'] !== $locked->purpose)
                || (array_key_exists('requested_outcome', $data) && $data['requested_outcome'] !== $locked->requested_outcome);

            $requester = OrganizationalUnit::with('organization')->findOrFail($newRequesterId);
            abort_unless($requester->isFormalGovernanceUnit() && $requester->status === 'ACTIVE', 422, 'INVALID_REQUESTING_DIRECTORATE');
            abort_unless($this->canManageRequester($actor, $requester->id), 403, 'FORBIDDEN');
            $authority = $this->activeAuthorityFor((new MeetingRequest())->setRelation('requestingUnit', $requester));
            $this->ensureRequesterApproverExists($requester->id);

            if (array_key_exists('participant_ids', $data)) {
                $ids = array_values(array_unique(array_map('intval', $data['participant_ids'])));
                $this->assertRequesterParticipants((new MeetingRequest())->setRelation('requestingUnit', $requester), $ids);
                $locked->participants()->whereIn('participant_type', ['PROPOSED', 'FINAL'])->delete();
                $locked->nominations()->delete();
                foreach ($ids as $employeeId) {
                    $employee = Employee::findOrFail($employeeId);
                    $locked->participants()->create(['employee_id' => $employeeId, 'participant_type' => 'PROPOSED', 'created_by' => $actor->id]);
                    $locked->nominations()->create(['employee_id' => $employeeId, 'organizational_unit_id' => $employee->organizational_unit_id, 'nominated_by_unit_id' => $requester->id, 'nominated_by_user_id' => $actor->id, 'participant_stage' => 'PROPOSED', 'nomination_status' => 'NOMINATED']);
                }
                unset($data['participant_ids']);
            }

            foreach (['requesting_unit_id', 'flow_type'] as $key) unset($data[$key]);
            $locked->update($data);
            if ($newRequesterId !== $oldRequesterId) {
                $locked->units()->where('role', 'REQUESTER')->update(['organizational_unit_id' => $newRequesterId]);
                $locked->update(['requesting_unit_id' => $newRequesterId, 'lead_coordinator_unit_id' => $newRequesterId]);
                $locked->authorityContext()->update([
                    'organization_authority_id' => $authority->id,
                    'authority_type' => $authority->authority_type,
                    'authority_office_unit_id' => $authority->authority_office_unit_id,
                    'authority_position_id' => $authority->authority_position_id,
                    'authority_employee_id' => null,
                    'authority_decision_status' => 'PENDING',
                ]);
            }

            if ($material) {
                $from = $locked->status;
                if (in_array($from, [MeetingRequestStatus::RETURNED_BY_AUTHORITY_OFFICE->value, MeetingRequestStatus::RETURNED_BY_HIGH_AUTHORITY->value], true)) {
                    $this->updateStatus($locked, MeetingRequestStatus::PENDING_REQUESTER_APPROVAL);
                    $locked->update(['requester_approved_by' => null, 'requester_approved_at' => null, 'approved_by' => null, 'approved_at' => null]);
                }
                $this->record($locked, $actor, 'MATERIAL_CHANGE_REQUIRES_REAPPROVAL', $from, $locked->status, null, ['requester_unit_id' => $newRequesterId]);
            } else {
                $this->record($locked, $actor, 'UPDATED', $locked->status, $locked->status, null, ['material' => false]);
            }

            return $this->responseRequest($locked);
        });
    }

    private function ensureRequesterApproverExists(int $unitId): void
    {
        abort_unless(User::whereHas('roleScopes', fn ($q) => $q->where('organizational_unit_id', $unitId)->whereHas('role', fn ($role) => $role->where('name', 'DIRECTOR')->where('guard_name', 'sanctum')))->exists(), 422, 'UNAUTHORIZED_APPROVER');
    }
}
