<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CreateMultiDirectorateMeetingRequest
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $data): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.create'), 403, 'FORBIDDEN');

            $requester = OrganizationalUnit::findOrFail((int) $data['requesting_unit_id']);
            $targetIds = array_values(array_unique(array_map('intval', $data['target_unit_ids'] ?? [])));
            $targets = OrganizationalUnit::whereIn('id', $targetIds)->get()->keyBy('id');
            abort_unless($targets->count() === count($targetIds), 422, 'INVALID_TARGET_DIRECTORATE');

            $rows = collect([['organizational_unit_id' => $requester->id, 'role' => 'REQUESTER']])
                ->concat($targets->map(fn (OrganizationalUnit $unit) => ['organizational_unit_id' => $unit->id, 'role' => 'TARGET'])->values());
            $unitObjects = collect([$requester])->concat($targets->values());
            $unitRows = $rows->map(fn (array $row) => (object) ['organizational_unit_id' => $row['organizational_unit_id'], 'role' => $row['role'], 'unit' => $unitObjects->firstWhere('id', $row['organizational_unit_id'])]);
            $this->assertMultiUnitSet($unitRows);
            abort_unless($this->canManageUnit($actor, $requester->id), 403, 'FORBIDDEN');

            $this->ensureAuthorizedApproverExists($requester->id);
            foreach ($targets as $target) $this->ensureAuthorizedApproverExists($target->id);

            $employeeIds = array_values(array_unique(array_map('intval', $data['participant_ids'] ?? [])));
            $this->assertParticipantEmployees($employeeIds, $unitObjects);

            $request = MeetingRequest::create([
                'flow_type' => 'MULTI_DIRECTORATE',
                'requesting_unit_id' => $requester->id,
                'created_by' => $actor->id,
                'subject' => $data['subject'],
                'purpose' => $data['purpose'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'],
                'preferred_date' => $data['preferred_date'] ?? null,
                'preferred_start_time' => $data['preferred_start_time'] ?? null,
                'estimated_duration_minutes' => $data['estimated_duration_minutes'] ?? null,
                'proposed_location' => $data['proposed_location'] ?? null,
                'status' => MeetingRequestStatus::DRAFT->value,
                'lead_coordinator_unit_id' => $requester->id,
                'lead_coordinator_user_id' => $actor->id,
            ]);

            $request->units()->createMany($rows->all());
            foreach ($targets as $target) {
                $request->unitReviews()->create([
                    'organizational_unit_id' => $target->id,
                    'review_status' => 'PENDING_REVIEW',
                ]);
            }

            foreach ($employeeIds as $employeeId) {
                $employee = Employee::findOrFail($employeeId);
                $owner = $this->topDirectorate($employee->organizational_unit_id);
                abort_unless($owner, 422, 'PARTICIPANT_OUTSIDE_MULTI_DIRECTORATE_SCOPE');
                $request->participants()->create([
                    'employee_id' => $employeeId,
                    'participant_type' => 'PROPOSED',
                    'created_by' => $actor->id,
                ]);
                $request->nominations()->create([
                    'employee_id' => $employeeId,
                    'organizational_unit_id' => $owner->id,
                    'nominated_by_unit_id' => $requester->id,
                    'nominated_by_user_id' => $actor->id,
                    'participant_stage' => 'PROPOSED',
                    'nomination_status' => 'NOMINATED',
                ]);
            }

            $this->record($request, $actor, 'CREATED', null, MeetingRequestStatus::DRAFT->value, null, [
                'flow_type' => 'MULTI_DIRECTORATE',
                'target_unit_ids' => $targets->keys()->values()->all(),
            ]);

            return $this->responseRequest($request);
        });
    }
}
