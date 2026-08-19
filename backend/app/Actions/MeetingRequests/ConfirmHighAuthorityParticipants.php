<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Models\MeetingAuthorityParticipantConfirmation;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmHighAuthorityParticipants
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow04Request($request);
            $this->assertActiveActor($actor);
            $context = $locked->authorityContext;
            abort_unless($context && $this->canAuthorityOffice($actor, $context->authority_office_unit_id) && $actor->can('authority-participant.confirm'), 403, 'UNAUTHORIZED_AUTHORITY_OFFICE_USER');
            abort_unless(in_array($locked->status, ['AUTHORITY_SCHEDULING', 'SCHEDULE_CONFIRMED'], true), 422, 'AUTHORITY_SCHEDULING_REQUIRED');
            $holder = $this->assertPersistedHolderAvailable($locked);
            abort_unless($locked->nominations()->where('participant_stage', 'FINAL')->where('nominated_by_unit_id', $context->authority_office_unit_id)->where('employee_id', $holder->id)->exists(), 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');
            abort_if(MeetingAuthorityParticipantConfirmation::where('meeting_request_id', $locked->id)->where('side', 'AUTHORITY')->exists(), 422, 'AUTHORITY_PARTICIPANTS_ALREADY_CONFIRMED');

            MeetingAuthorityParticipantConfirmation::create(['meeting_request_id' => $locked->id, 'side' => 'AUTHORITY', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            $this->record($locked, $actor, 'AUTHORITY_PARTICIPANTS_CONFIRMED', $locked->status, $locked->status, null, ['authority_office_unit_id' => $context->authority_office_unit_id, 'authority_employee_id' => $holder->id]);
            return $this->responseRequest($locked);
        });
    }
}
