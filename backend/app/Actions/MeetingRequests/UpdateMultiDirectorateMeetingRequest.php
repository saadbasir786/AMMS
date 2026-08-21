<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateMultiDirectorateMeetingRequest
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $locked = $this->lockFlow03Request($request);
            $this->assertActiveActor($actor);
            $requester = $this->requesterUnit($locked);
            abort_unless($actor->can('meeting-request.update') && $this->canManageUnit($actor, $requester->id), 403, 'FORBIDDEN');
            abort_unless(in_array($locked->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'TARGET_ACTION_REQUIRED'], true), 422, 'REQUEST_NOT_EDITABLE');

            $newRequesterId = array_key_exists('requesting_unit_id', $data) ? (int) $data['requesting_unit_id'] : $requester->id;
            $oldTargetIds = $locked->units->where('role', 'TARGET')->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $newTargetIds = array_key_exists('target_unit_ids', $data)
                ? collect(array_unique(array_map('intval', $data['target_unit_ids'])))->sort()->values()->all()
                : $oldTargetIds;
            $material = $newRequesterId !== (int) $requester->id
                || $newTargetIds !== $oldTargetIds
                || (array_key_exists('subject', $data) && $data['subject'] !== $locked->subject)
                || (array_key_exists('purpose', $data) && $data['purpose'] !== $locked->purpose)
                || array_key_exists('flow_type', $data);

            $newRequester = OrganizationalUnit::findOrFail($newRequesterId);
            $targetUnits = OrganizationalUnit::whereIn('id', $newTargetIds)->get()->keyBy('id');
            abort_unless($targetUnits->count() === count($newTargetIds), 422, 'INVALID_TARGET_DIRECTORATE');
            $rows = collect([['organizational_unit_id' => $newRequester->id, 'role' => 'REQUESTER']])->concat($targetUnits->map(fn ($unit) => ['organizational_unit_id' => $unit->id, 'role' => 'TARGET'])->values());
            $objects = collect([$newRequester])->concat($targetUnits->values());
            $checkRows = $rows->map(fn ($row) => (object) ['organizational_unit_id' => $row['organizational_unit_id'], 'role' => $row['role'], 'unit' => $objects->firstWhere('id', $row['organizational_unit_id'])]);
            $this->assertMultiUnitSet($checkRows);
            abort_unless($this->canManageUnit($actor, $newRequester->id), 403, 'FORBIDDEN');
            $this->ensureAuthorizedApproverExists($newRequester->id);
            foreach ($targetUnits as $target) $this->ensureAuthorizedApproverExists($target->id);

            if (array_key_exists('participant_ids', $data)) {
                $ids = array_values(array_unique(array_map('intval', $data['participant_ids'])));
                $this->assertParticipantEmployees($ids, $objects);
                $locked->participants()->where('participant_type', 'PROPOSED')->delete();
                $locked->nominations()->where('participant_stage', 'PROPOSED')->delete();
                foreach ($ids as $employeeId) {
                    $employee = Employee::with('unit.parent')->findOrFail($employeeId);
                    $owner = $this->topDirectorate($employee->organizational_unit_id);
                    $locked->participants()->create(['employee_id' => $employeeId, 'participant_type' => 'PROPOSED', 'created_by' => $actor->id]);
                    $locked->nominations()->create(['employee_id' => $employeeId, 'organizational_unit_id' => $owner->id, 'nominated_by_unit_id' => $newRequester->id, 'nominated_by_user_id' => $actor->id, 'participant_stage' => 'PROPOSED', 'nomination_status' => 'NOMINATED']);
                }
                unset($data['participant_ids']);
            }

            foreach (['requesting_unit_id', 'target_unit_ids', 'flow_type'] as $key) unset($data[$key]);
            $locked->update($data);
            if ($material) {
                $locked->units()->delete();
                $locked->units()->createMany($rows->all());
                $locked->unitReviews()->delete();
                foreach ($targetUnits as $target) $locked->unitReviews()->create(['organizational_unit_id' => $target->id, 'review_status' => 'PENDING_REVIEW']);
                $from = $locked->status;
                $locked->update([
                    'requesting_unit_id' => $newRequester->id,
                    'lead_coordinator_unit_id' => $newRequester->id,
                    'requester_approved_by' => null,
                    'requester_approved_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'status' => MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value,
                ]);
                $this->record($locked, $actor, 'MATERIAL_CHANGE_REQUIRES_REAPPROVAL', $from, $locked->status, null, ['previous_target_unit_ids' => $oldTargetIds, 'target_unit_ids' => $newTargetIds]);
                $this->notifyUnit($newRequester->id, ['DIRECTOR'], 'درخواست چندریاستی نیازمند تأیید مجدد است', 'به‌دلیل تغییر اساسی، تأیید مجدد ریاست درخواست‌کننده لازم است.', $locked);
            } else {
                $this->record($locked, $actor, 'UPDATED', $locked->status, $locked->status, null, ['material' => false]);
            }

            return $this->responseRequest($locked);
        });
    }
}
