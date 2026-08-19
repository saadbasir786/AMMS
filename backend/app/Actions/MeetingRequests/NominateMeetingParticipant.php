<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\MeetingRequestParticipantNomination;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NominateMeetingParticipant
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $data): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $data): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-participant.nominate'), 403, 'FORBIDDEN');
            $locked = $this->lockFlow02Request($request);
            abort_unless(in_array($locked->status, ['BILATERAL_APPROVED', 'SCHEDULING'], true), 422, 'BILATERAL_APPROVAL_REQUIRED');
            $requester = $this->unitFor($locked, 'REQUESTER');
            $target = $this->unitFor($locked, 'TARGET');
            $side = \App\Models\OrganizationalUnit::findOrFail($data['nominated_by_unit_id']);
            abort_unless(in_array($side->id, [$requester->id, $target->id], true), 403, 'FORBIDDEN');
            abort_unless($this->canCoordinateUnit($actor, $side->id), 403, 'FORBIDDEN');
            $employee = Employee::with('unit')->findOrFail($data['employee_id']);
            abort_unless($employee->status === 'ACTIVE', 422, 'INVALID_PARTICIPANT');
            abort_unless($side->isWithinScope($employee->organizational_unit_id), 422, 'PARTICIPANT_OUTSIDE_ALLOWED_UNITS');
            $existing = MeetingRequestParticipantNomination::where('meeting_request_id', $locked->id)->where('employee_id', $employee->id)->where('participant_stage', 'FINAL')->first();
            $nomination = MeetingRequestParticipantNomination::updateOrCreate(
                ['meeting_request_id' => $locked->id, 'employee_id' => $employee->id, 'participant_stage' => 'FINAL'],
                ['organizational_unit_id' => $employee->organizational_unit_id, 'nominated_by_unit_id' => $side->id, 'nominated_by_user_id' => $actor->id, 'nomination_status' => 'NOMINATED'],
            );
            $this->record($locked, $actor, $existing ? 'PARTICIPANT_REPLACED' : 'PARTICIPANT_NOMINATED', $locked->status, $locked->status, null, ['nomination_id' => $nomination->id, 'unit_id' => $side->id, 'employee_id' => $employee->id]);
            return $this->responseRequest($locked);
        });
    }
}
