<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmMultiHighAuthorityParticipants
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow05Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canAuthorityOffice($actor, $context->authority_office_unit_id) && $actor->can('authority-participant.confirm'), 403, 'UNAUTHORIZED_AUTHORITY_OFFICE_USER');
            abort_unless($locked->status === 'SCHEDULE_CONFIRMED', 422, 'AUTHORITY_SCHEDULE_NOT_CONFIRMED');
            $holder = $this->persistedHolder($locked);
            $hasHolder = $locked->nominations()->where('participant_stage', 'FINAL')->where('nomination_status', 'NOMINATED')->where('employee_id', $holder->id)->where('nominated_by_unit_id', $context->authority_office_unit_id)->exists();
            abort_unless($hasHolder, 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');
            $locked->authorityParticipantConfirmations()->updateOrCreate(['side' => 'AUTHORITY'], ['confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            $locked->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $context->authority_office_unit_id)->update(['nomination_status' => 'CONFIRMED']);
            $this->record($locked, $actor, 'AUTHORITY_PARTICIPANTS_CONFIRMED', $locked->status, $locked->status, null, ['authority_office_unit_id' => $context->authority_office_unit_id, 'authority_employee_id' => $holder->id]);
            return $this->responseRequest($locked);
        });
    }
}
