<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveRequesterSideRequest
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.approve'), 403, 'FORBIDDEN');
            $locked = $this->lockFlow02Request($request);
            $requester = $this->unitFor($locked, 'REQUESTER');
            abort_unless($actor->hasScopedRole('DIRECTOR', $requester->id), 403, 'UNAUTHORIZED_REQUESTER_APPROVER');
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::PENDING_REQUESTER_APPROVAL, 422, 'REQUEST_NOT_PENDING_APPROVAL');
            $from = $this->updateStatus($locked, MeetingRequestStatus::REQUESTER_APPROVED);
            $locked->update(['requester_approved_by' => $actor->id, 'requester_approved_at' => now(), 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->record($locked, $actor, 'REQUESTER_APPROVED', $from, MeetingRequestStatus::REQUESTER_APPROVED->value);
            $this->assertTransition($locked, MeetingRequestStatus::SUBMITTED_TO_TARGET);
            $from = $this->updateStatus($locked, MeetingRequestStatus::SUBMITTED_TO_TARGET);
            $this->record($locked, $actor, 'REQUEST_SENT_TO_TARGET', $from === MeetingRequestStatus::SUBMITTED_TO_TARGET->value ? MeetingRequestStatus::REQUESTER_APPROVED->value : $from, MeetingRequestStatus::SUBMITTED_TO_TARGET->value);
            $target = $this->unitFor($locked, 'TARGET');
            $this->notifyUnit($target->id, ['EXECUTIVE_MANAGEMENT'], 'درخواست وارده از ریاست دیگر', 'یک درخواست بین‌ریاستی پس از تأیید ریاست درخواست‌کننده به ریاست شما ارسال شد.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
