<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateMultiHighAuthorityMeetingRequest
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $oldRequester = $this->requesterUnit($locked);
            abort_unless($locked->created_by === $actor->id && $actor->can('meeting-request.update') && $this->canManageUnit($actor, $oldRequester->id), 403, 'FORBIDDEN');
            abort_unless(in_array($locked->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'DIRECTORATE_ACTION_REQUIRED', 'RETURNED_BY_AUTHORITY_OFFICE', 'RETURNED_BY_HIGH_AUTHORITY'], true), 422, 'REQUEST_NOT_EDITABLE');

            $newRequesterId = array_key_exists('requesting_unit_id', $data) ? (int) $data['requesting_unit_id'] : (int) $oldRequester->id;
            $oldParticipatingIds = $locked->units->where('role', 'PARTICIPATING')->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $newParticipatingIds = array_key_exists('participating_unit_ids', $data) ? array_values(array_unique(array_map('intval', $data['participating_unit_ids']))) : $oldParticipatingIds;
            $material = $newRequesterId !== (int) $oldRequester->id || $newParticipatingIds !== $oldParticipatingIds || collect(['subject', 'purpose', 'requested_outcome'])->contains(fn ($key) => array_key_exists($key, $data) && (string) $data[$key] !== (string) $locked->{$key});

            $newRequester = OrganizationalUnit::findOrFail($newRequesterId);
            $participating = OrganizationalUnit::whereIn('id', $newParticipatingIds)->get()->keyBy('id');
            $rows = collect([['organizational_unit_id' => $newRequester->id, 'role' => 'REQUESTER']])->concat($participating->map(fn ($unit) => ['organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING'])->values());
            $objects = $rows->map(fn ($row) => (object) ['organizational_unit_id' => $row['organizational_unit_id'], 'role' => $row['role'], 'unit' => $row['organizational_unit_id'] === $newRequester->id ? $newRequester : $participating->get($row['organizational_unit_id'])]);
            $this->assertUnitSet($objects);
            abort_unless($this->canManageUnit($actor, $newRequester->id), 403, 'FORBIDDEN');
            foreach ($participating as $unit) abort_unless(User::whereHas('roleScopes', fn ($q) => $q->where('organizational_unit_id', $unit->id)->whereHas('role', fn ($r) => $r->where('name', 'DIRECTOR')->where('guard_name', 'sanctum')))->exists(), 422, 'UNAUTHORIZED_APPROVER');
            foreach (['requesting_unit_id', 'participating_unit_ids'] as $key) unset($data[$key]);
            $locked->update($data);

            if ($material) {
                $locked->units()->delete();
                $locked->units()->createMany($rows->all());
                $locked->unitReviews()->delete();
                foreach ($participating as $unit) $locked->unitReviews()->create(['organizational_unit_id' => $unit->id, 'review_status' => 'PENDING_REVIEW']);
                $from = $locked->status;
                $locked->update(['requesting_unit_id' => $newRequester->id, 'lead_coordinator_unit_id' => $newRequester->id, 'requester_approved_by' => null, 'requester_approved_at' => null, 'status' => MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value]);
                $this->record($locked, $actor, 'MATERIAL_CHANGE_REQUIRES_REAPPROVAL', $from, $locked->status, null, ['previous_participating_unit_ids' => $oldParticipatingIds, 'participating_unit_ids' => $newParticipatingIds]);
            } else {
                $this->record($locked, $actor, 'UPDATED', $locked->status, $locked->status, null, ['material' => false]);
            }
            return $this->responseRequest($locked);
        });
    }
}
