<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateHighAuthorityRequesterParticipants
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $employeeIds): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $employeeIds): MeetingRequest {
            $locked = $this->lockHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            abort_unless(in_array($locked->status, ['AUTHORITY_SCHEDULING', 'SCHEDULE_CONFIRMED'], true), 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            abort_unless($this->canManageRequester($actor, $locked->requesting_unit_id), 403, 'FORBIDDEN');
            $ids = array_values(array_unique(array_map('intval', $employeeIds)));
            $this->assertRequesterParticipants($locked, $ids);

            $oldRequesterIds = $locked->nominations()
                ->where('participant_stage', 'FINAL')
                ->where('nominated_by_unit_id', $locked->requesting_unit_id)
                ->pluck('employee_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $locked->nominations()
                ->where('participant_stage', 'FINAL')
                ->where('nominated_by_unit_id', $locked->requesting_unit_id)
                ->delete();
            if ($oldRequesterIds) {
                $locked->participants()->where('participant_type', 'FINAL')->whereIn('employee_id', $oldRequesterIds)->delete();
            }

            foreach ($ids as $employeeId) {
                $employee = Employee::findOrFail($employeeId);
                $locked->nominations()->create([
                    'employee_id' => $employeeId,
                    'organizational_unit_id' => $employee->organizational_unit_id,
                    'nominated_by_unit_id' => $locked->requesting_unit_id,
                    'nominated_by_user_id' => $actor->id,
                    'participant_stage' => 'FINAL',
                    'nomination_status' => 'NOMINATED',
                ]);
                if (!$locked->participants()->where('participant_type', 'FINAL')->where('employee_id', $employeeId)->exists()) {
                    $locked->participants()->create(['employee_id' => $employeeId, 'participant_type' => 'FINAL', 'created_by' => $actor->id]);
                }
            }
            $locked->authorityParticipantConfirmations()->where('side', 'REQUESTER')->delete();
            $this->record($locked, $actor, 'REQUESTER_PARTICIPANTS_UPDATED', $locked->status, $locked->status, null, ['employee_ids' => $ids]);
            return $this->responseRequest($locked);
        });
    }
}
