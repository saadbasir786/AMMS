<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\AuthorityInitiatedWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmAuthorityInitiatedAuthorityParticipants
{
    use AuthorityInitiatedWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $request = $this->lockAuthorityInitiatedRequest($request);
            $officeId = (int) ($request->authorityContext?->authority_office_unit_id ?? 0);
            abort_unless($officeId && $actor->isActive() && $actor->can('authority-participant.confirm') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('AUTHORITY_OFFICE', $officeId)), 403, 'UNAUTHORIZED_AUTHORITY_PARTICIPANT_ACTION');
            abort_unless(in_array($request->status, ['AUTHORITY_SCHEDULING', 'SCHEDULE_CONFIRMED'], true), 422, 'AUTHORITY_SCHEDULE_REQUIRED');
            $holder = $this->currentAuthorityHolder($request->authorityContext->organizationAuthority);
            abort_unless($request->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $officeId)->where('employee_id', $holder->id)->exists(), 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');
            $request->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $officeId)->update(['nomination_status' => 'CONFIRMED']);
            $request->authorityParticipantConfirmations()->updateOrCreate(['side' => 'AUTHORITY'], ['confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            $this->record06($request, $actor, 'AUTHORITY_PARTICIPANTS_CONFIRMED', $request->status, $request->status, null, ['authority_employee_id' => $holder->id]);
            return $this->response06($request);
        });
    }
}
