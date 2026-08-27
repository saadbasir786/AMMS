<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageAuthorityInitiatedUnitParticipants
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, int $unitId, array $employeeIds): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $unitId, $employeeIds): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            $this->assertActiveActor($actor);
            $unit = $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->first()?->unit;
            abort_unless($unit instanceof OrganizationalUnit, 403, 'DIRECTORATE_NOT_PART_OF_DIRECTIVE');
            abort_unless($actor->can('meeting-participant.nominate') && $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId), 403, 'UNAUTHORIZED_PARTICIPATING_UNIT_ACTION');
            abort_unless(in_array($request->status, ['AUTHORITY_SCHEDULING', 'SCHEDULE_CONFIRMED'], true), 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            abort_if($request->participantConfirmations()->where('organizational_unit_id', $unitId)->exists(), 422, 'UNIT_PARTICIPANTS_ALREADY_CONFIRMED');

            $ids = array_values(array_unique(array_map('intval', $employeeIds)));
            $this->assertEmployeesInUnit($ids, $unit);
            $oldIds = $request->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $unitId)->pluck('employee_id')->all();
            $request->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $unitId)->delete();
            if ($oldIds) $request->participants()->where('participant_type', 'FINAL')->whereIn('employee_id', $oldIds)->delete();
            foreach ($ids as $employeeId) {
                $employee = Employee::findOrFail($employeeId);
                $request->nominations()->create(['employee_id' => $employeeId, 'organizational_unit_id' => $employee->organizational_unit_id, 'nominated_by_unit_id' => $unitId, 'nominated_by_user_id' => $actor->id, 'participant_stage' => 'FINAL', 'nomination_status' => 'NOMINATED']);
                $request->participants()->create(['employee_id' => $employeeId, 'participant_type' => 'FINAL', 'created_by' => $actor->id]);
            }
            $this->recordAuthorityInitiated($request, $actor, 'PARTICIPANTS_UPDATED', $request->status, $request->status, null, ['organizational_unit_id' => $unitId, 'employee_ids' => $ids]);
            return $this->responseAuthorityInitiated($request);
        });
    }
}
