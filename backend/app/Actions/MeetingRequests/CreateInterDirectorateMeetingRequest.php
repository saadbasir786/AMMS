<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateInterDirectorateMeetingRequest
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $data): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.create'), 403, 'FORBIDDEN');
            $requester = \App\Models\OrganizationalUnit::findOrFail($data['requesting_unit_id']);
            $target = \App\Models\OrganizationalUnit::findOrFail($data['target_unit_id']);
            $this->ensureSameOrganizationAndDistinct($requester, $target);
            abort_unless($this->canManageUnit($actor, $requester->id), 403, 'FORBIDDEN');
            $this->ensureAuthorizedApproverExists($requester->id);
            $this->ensureAuthorizedApproverExists($target->id);
            $employeeIds = array_values(array_unique($data['participant_ids'] ?? []));
            $this->assertParticipantEmployees($employeeIds, $requester, $target);

            $request = MeetingRequest::create([
                'flow_type' => 'INTER_DIRECTORATE',
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
            $request->units()->createMany([
                ['organizational_unit_id' => $requester->id, 'role' => 'REQUESTER'],
                ['organizational_unit_id' => $target->id, 'role' => 'TARGET'],
            ]);
            foreach ($employeeIds as $employeeId) {
                $employee = \App\Models\Employee::findOrFail($employeeId);
                $sideUnit = $requester->isWithinScope($employee->organizational_unit_id) ? $requester : $target;
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
            $this->record($request, $actor, 'CREATED', null, MeetingRequestStatus::DRAFT->value, null, ['flow_type' => 'INTER_DIRECTORATE']);
            return $this->responseRequest($request);
        });
    }
}
