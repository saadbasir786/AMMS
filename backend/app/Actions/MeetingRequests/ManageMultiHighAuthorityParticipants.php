<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageMultiHighAuthorityParticipants
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $employeeIds): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $employeeIds): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canAuthorityOffice($actor, $context->authority_office_unit_id) && $actor->can('authority-participant.manage'), 403, 'UNAUTHORIZED_AUTHORITY_OFFICE_USER');
            abort_unless($locked->status === 'SCHEDULE_CONFIRMED', 422, 'AUTHORITY_SCHEDULE_NOT_CONFIRMED');
            $holder = $this->persistedHolder($locked);
            $ids = array_values(array_unique(array_map('intval', $employeeIds)));
            abort_unless(in_array($holder->id, $ids, true), 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');
            $office = $context->authorityOfficeUnit->load('children');
            $employees = Employee::with(['unit', 'position'])->whereIn('id', $ids)->get();
            abort_if($employees->count() !== count($ids), 422, 'INVALID_AUTHORITY_PARTICIPANT');
            foreach ($employees as $employee) {
                abort_unless($employee->status === 'ACTIVE' && ($employee->id === $holder->id || $office->isWithinScope((int) $employee->organizational_unit_id)), 422, 'INVALID_AUTHORITY_PARTICIPANT');
            }
            $oldIds = $locked->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $context->authority_office_unit_id)->pluck('employee_id')->all();
            $locked->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $context->authority_office_unit_id)->delete();
            if ($oldIds) $locked->participants()->where('participant_type', 'FINAL')->whereIn('employee_id', $oldIds)->delete();
            foreach ($employees as $employee) {
                $locked->nominations()->create(['employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'nominated_by_unit_id' => $context->authority_office_unit_id, 'nominated_by_user_id' => $actor->id, 'participant_stage' => 'FINAL', 'nomination_status' => 'NOMINATED']);
                $locked->participants()->create(['employee_id' => $employee->id, 'participant_type' => 'FINAL', 'created_by' => $actor->id]);
            }
            $locked->authorityParticipantConfirmations()->where('side', 'AUTHORITY')->delete();
            $this->record($locked, $actor, 'AUTHORITY_PARTICIPANTS_UPDATED', $locked->status, $locked->status, null, ['authority_office_unit_id' => $context->authority_office_unit_id, 'employee_ids' => $ids]);
            return $this->responseRequest($locked);
        });
    }
}
