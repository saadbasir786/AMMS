<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiHighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitMultiHighAuthorityRequest
{
    use MultiHighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockMultiHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            abort_unless($locked->status === MeetingRequestStatus::DRAFT->value, 422, 'REQUEST_NOT_EDITABLE');
            abort_unless($actor->id === $locked->created_by && $this->canManageUnit($actor, $this->requesterUnit($locked)->id), 403, 'FORBIDDEN');
            abort_unless($this->participatingUnits($locked)->count() >= 1, 422, 'MULTIPLE_DIRECTORATES_REQUIRED_FOR_MULTI_HIGH_AUTHORITY');
            $this->activeAuthorityFor($locked);
            foreach ($this->formalUnits($locked) as $unit) abort_unless($unit->status === 'ACTIVE' && $unit->unit_type === 'DIRECTORATE', 422, 'INVALID_PARTICIPATING_DIRECTORATE');
            $from = $this->transition($locked, MeetingRequestStatus::PENDING_REQUESTER_APPROVAL);
            $this->record($locked, $actor, 'SUBMITTED_FOR_REQUESTER_APPROVAL', $from, $locked->status, null, ['requester_unit_id' => $this->requesterUnit($locked)->id]);
            $this->notifyUnit($this->requesterUnit($locked)->id, ['DIRECTOR'], 'درخواست چندریاستی جدید', 'درخواست چندریاستی برای تأیید رئیس ریاست آماده است.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
