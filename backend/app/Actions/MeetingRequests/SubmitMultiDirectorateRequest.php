<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubmitMultiDirectorateRequest
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockMultiDirectorateRequest($request);
            $this->assertActiveActor($actor);
            abort_unless($locked->created_by === $actor->id && $actor->can('meeting-request.submit'), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::DRAFT->value, 422, 'REQUEST_NOT_EDITABLE');
            $this->assertMultiUnitSet($locked->units);
            abort_unless(filled($locked->subject) && filled($locked->purpose), 422, 'REQUEST_INCOMPLETE');
            abort_unless($locked->unitReviews->count() === $locked->units->where('role', 'TARGET')->count(), 422, 'TARGET_REVIEWS_REQUIRED');

            $from = $locked->status;
            $locked->update(['status' => MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value, 'request_number' => $locked->request_number ?: $this->nextNumber('meeting_requests', 'MR')]);
            $this->record($locked, $actor, 'SUBMITTED_FOR_REQUESTER_APPROVAL', $from, $locked->status, null, ['unit_id' => $this->requesterUnit($locked)->id]);
            $this->notifyUnit($this->requesterUnit($locked)->id, ['DIRECTOR'], 'درخواست چندریاستی برای تأیید داخلی', 'یک درخواست جلسهٔ چندریاستی منتظر تأیید ریاست شما است.', $locked);

            return $this->responseRequest($locked);
        });
    }
}
