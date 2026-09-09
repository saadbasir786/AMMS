<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitHighAuthorityMeetingRequest
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            abort_unless($locked->created_by === $actor->id && $this->canManageRequester($actor, $locked->requesting_unit_id), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::DRAFT->value, 422, 'REQUEST_NOT_DRAFT');
            $this->activeAuthorityFor($locked);
            abort_unless($locked->requestingUnit?->status === 'ACTIVE' && $locked->requestingUnit?->isFormalGovernanceUnit(), 422, 'INVALID_REQUESTING_DIRECTORATE');
            abort_unless(filled($locked->subject) && filled($locked->purpose) && filled($locked->meeting_justification) && filled($locked->requested_outcome), 422, 'REQUEST_INCOMPLETE');

            $from = $this->updateStatus($locked, MeetingRequestStatus::PENDING_REQUESTER_APPROVAL);
            if (!$locked->request_number) $locked->update(['request_number' => $this->nextNumber('meeting_requests', 'MR')]);
            $this->record($locked, $actor, 'SUBMITTED_FOR_REQUESTER_APPROVAL', $from, $locked->status);
            $this->notifyUsersForUnit($locked->requesting_unit_id, ['DIRECTOR'], 'درخواست جلسه با مقام عالی', 'یک درخواست جلسه با مقام عالی برای تأیید داخلی ارسال شده است.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
