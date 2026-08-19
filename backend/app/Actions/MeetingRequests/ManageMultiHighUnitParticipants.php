<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageMultiHighUnitParticipants
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId, array $employeeIds): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId, $employeeIds): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $unit = $this->formalUnits($locked)->firstWhere('id', $unitId);
            abort_unless($unit, 403, 'PARTICIPATING_UNIT_NOT_PART_OF_REQUEST');
            abort_unless($actor->can('meeting-participant.nominate') && $this->canParticipatingUnit($actor, $unitId), 403, 'UNAUTHORIZED_PARTICIPATING_UNIT_ACTION');
            abort_unless(in_array($locked->status, ['SCHEDULE_CONFIRMED', 'AUTHORITY_SCHEDULING'], true), 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            $ids = array_values(array_unique(array_map('intval', $employeeIds)));
            $this->assertEmployeesInUnit($ids, $unit);
            $oldIds = $locked->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $unitId)->pluck('employee_id')->all();
            $locked->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $unitId)->delete();
            if ($oldIds) $locked->participants()->where('participant_type', 'FINAL')->whereIn('employee_id', $oldIds)->delete();
            foreach ($ids as $employeeId) {
                $locked->nominations()->create(['employee_id' => $employeeId, 'organizational_unit_id' => $unitId, 'nominated_by_unit_id' => $unitId, 'nominated_by_user_id' => $actor->id, 'participant_stage' => 'FINAL', 'nomination_status' => 'NOMINATED']);
                $locked->participants()->create(['employee_id' => $employeeId, 'participant_type' => 'FINAL', 'created_by' => $actor->id]);
            }
            $locked->participantConfirmations()->where('organizational_unit_id', $unitId)->delete();
            $this->record($locked, $actor, 'PARTICIPANTS_UPDATED', $locked->status, $locked->status, null, ['organizational_unit_id' => $unitId, 'unit_name' => $unit->name, 'employee_ids' => $ids]);
            return $this->responseRequest($locked);
        });
    }
}
