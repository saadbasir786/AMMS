<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NominateMultiMeetingParticipant
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $locked = $this->lockFlow03Request($request);
            $this->assertActiveActor($actor);
            abort_unless(in_array($locked->status, ['SCHEDULING', 'SCHEDULE_CONFIRMED'], true), 422, 'SCHEDULING_REQUIRED');
            $unitId = (int) $data['organizational_unit_id'];
            $unit = $this->assertParticipatingMember($locked, $unitId);
            abort_unless($this->canManageUnit($actor, $unit->id), 403, 'FORBIDDEN');
            abort_if($locked->participantConfirmations->contains('organizational_unit_id', $unitId), 422, 'PARTICIPANTS_ALREADY_CONFIRMED');

            $employee = Employee::with('unit.parent')->findOrFail((int) $data['employee_id']);
            abort_unless($employee->status === 'ACTIVE', 422, 'INVALID_PARTICIPANT');
            abort_unless($unit->isWithinScope($employee->organizational_unit_id), 422, 'PARTICIPANT_OUTSIDE_MULTI_DIRECTORATE_SCOPE');

            abort_if($locked->nominations->where('participant_stage', 'FINAL')->contains('employee_id', $employee->id), 422, 'PARTICIPANT_ALREADY_NOMINATED');
            $locked->nominations()->create([
                'employee_id' => $employee->id,
                'organizational_unit_id' => $unit->id,
                'nominated_by_unit_id' => $unit->id,
                'nominated_by_user_id' => $actor->id,
                'participant_stage' => 'FINAL',
                'nomination_status' => 'NOMINATED',
            ]);

            $this->record($locked, $actor, 'PARTICIPANT_NOMINATED', $locked->status, $locked->status, null, [
                'unit_id' => $unit->id,
                'employee_id' => $employee->id,
            ]);

            return $this->responseRequest($locked);
        });
    }
}
