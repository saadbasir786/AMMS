<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveHighAuthorityRequesterSide
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow04Request($request);
            $this->assertActiveActor($actor);
            abort_unless($this->canRequesterApprove($actor, $locked->requesting_unit_id), 403, 'FORBIDDEN');
            abort_unless($locked->status === MeetingRequestStatus::PENDING_REQUESTER_APPROVAL->value, 422, 'REQUEST_NOT_PENDING_REQUESTER_APPROVAL');
            $authority = $this->activeAuthorityFor($locked);

            $from = $this->updateStatus($locked, MeetingRequestStatus::REQUESTER_APPROVED);
            $locked->update(['requester_approved_by' => $actor->id, 'requester_approved_at' => now(), 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->record($locked, $actor, 'REQUESTER_APPROVED', $from, $locked->status, null, ['unit_id' => $locked->requesting_unit_id]);

            $from = $this->updateStatus($locked, MeetingRequestStatus::SUBMITTED_TO_AUTHORITY_OFFICE);
            $locked->authorityContext()->update(['office_review_status' => 'PENDING']);
            $this->record($locked, $actor, 'REQUEST_SENT_TO_AUTHORITY_OFFICE', $from, $locked->status, null, ['authority_office_unit_id' => $authority->authority_office_unit_id]);
            $this->notifyUsersForUnit($authority->authority_office_unit_id, ['AUTHORITY_OFFICE'], 'درخواست جدید در دفتر مقام', 'یک درخواست تأییدشده برای بررسی دفتر مقام دریافت شد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
