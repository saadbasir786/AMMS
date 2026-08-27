<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManageAuthorityInitiatedAuthorityParticipants
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request, array $employeeIds): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request, $employeeIds): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            $officeId = (int) ($request->authorityContext?->authority_office_unit_id ?? 0);
            abort_unless($officeId && $actor->isActive() && $actor->can('authority-participant.manage') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('AUTHORITY_OFFICE', $officeId)), 403, 'UNAUTHORIZED_AUTHORITY_PARTICIPANT_ACTION');
            abort_unless(in_array($request->status, ['AUTHORITY_SCHEDULING', 'SCHEDULE_CONFIRMED'], true), 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            $ids = array_values(array_unique(array_map('intval', $employeeIds)));
            $holder = $this->currentAuthorityHolder($request->authorityContext->organizationAuthority);
            abort_unless(in_array($holder->id, $ids, true), 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');
            $employees = Employee::whereIn('id', $ids)->get();
            abort_unless($employees->count() === count($ids), 422, 'INVALID_PARTICIPANT');
            foreach ($employees as $employee) {
                abort_unless($employee->status === 'ACTIVE' && ($employee->id === $holder->id || $employee->unit?->isWithinScope($officeId)), 422, 'PARTICIPANT_OUTSIDE_AUTHORITY_SCOPE');
            }
            $oldIds = $request->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $officeId)->pluck('employee_id')->all();
            $request->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $officeId)->delete();
            if ($oldIds) $request->participants()->where('participant_type', 'FINAL')->whereIn('employee_id', $oldIds)->delete();
            foreach ($ids as $employeeId) {
                $request->nominations()->create(['employee_id' => $employeeId, 'organizational_unit_id' => $officeId, 'nominated_by_unit_id' => $officeId, 'nominated_by_user_id' => $actor->id, 'participant_stage' => 'FINAL', 'nomination_status' => 'NOMINATED']);
                $request->participants()->create(['employee_id' => $employeeId, 'participant_type' => 'FINAL', 'created_by' => $actor->id]);
            }
            $request->authorityParticipantConfirmations()->where('side', 'AUTHORITY')->delete();
            $this->record06($request, $actor, 'AUTHORITY_PARTICIPANTS_UPDATED', $request->status, $request->status, null, ['employee_ids' => $ids]);
            return $this->response06($request);
        });
    }
}
