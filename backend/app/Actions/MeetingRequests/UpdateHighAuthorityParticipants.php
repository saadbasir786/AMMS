<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateHighAuthorityParticipants
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $employeeIds): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $employeeIds): MeetingRequest {
            $locked = $this->lockFlow04Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canAuthorityOffice($actor, $context->authority_office_unit_id) && $actor->can('authority-participant.manage'), 403, 'UNAUTHORIZED_AUTHORITY_OFFICE_USER');
            abort_unless(in_array($locked->status, ['AUTHORITY_SCHEDULING', 'SCHEDULE_CONFIRMED'], true), 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            $holder = $this->assertPersistedHolderAvailable($locked);
            $ids = array_values(array_unique(array_map('intval', $employeeIds)));
            $employees = Employee::with(['unit.parent'])->whereIn('id', $ids)->get();
            abort_if($employees->count() !== count($ids), 422, 'INVALID_AUTHORITY_PARTICIPANT');
            $office = $context->authorityOfficeUnit->load('children');
            foreach ($employees as $employee) {
                abort_unless($employee->status === 'ACTIVE', 422, 'INVALID_AUTHORITY_PARTICIPANT');
                $isHolder = $employee->id === $holder->id;
                $inOffice = $office->isWithinScope((int) $employee->organizational_unit_id);
                abort_unless($isHolder || $inOffice, 422, 'INVALID_AUTHORITY_PARTICIPANT');
            }
            abort_unless(in_array($holder->id, $ids, true), 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');

            $locked->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $context->authority_office_unit_id)->delete();
            $locked->participants()->where('participant_type', 'FINAL')->whereIn('employee_id', $ids)->delete();
            foreach ($employees as $employee) {
                $locked->nominations()->create(['employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'nominated_by_unit_id' => $context->authority_office_unit_id, 'nominated_by_user_id' => $actor->id, 'participant_stage' => 'FINAL', 'nomination_status' => 'NOMINATED']);
                if (!$locked->participants()->where('participant_type', 'FINAL')->where('employee_id', $employee->id)->exists()) {
                    $locked->participants()->create(['employee_id' => $employee->id, 'participant_type' => 'FINAL', 'created_by' => $actor->id]);
                }
            }
            $locked->authorityParticipantConfirmations()->where('side', 'AUTHORITY')->delete();
            $this->record($locked, $actor, 'AUTHORITY_PARTICIPANTS_UPDATED', $locked->status, $locked->status, null, ['employee_ids' => $ids, 'authority_office_unit_id' => $context->authority_office_unit_id]);
            return $this->responseRequest($locked);
        });
    }
}
