<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResubmitToTarget
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.resubmit'), 403, 'FORBIDDEN');
            $locked = $this->lockFlow02Request($request);
            $requester = $this->unitFor($locked, 'REQUESTER');
            abort_unless($this->canManageUnit($actor, $requester->id), 403, 'FORBIDDEN');
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::RETURNED_BY_TARGET, 422, 'REQUEST_NOT_AVAILABLE_TO_TARGET');
            $from = $this->updateStatus($locked, MeetingRequestStatus::RESUBMITTED_TO_TARGET);
            $this->record($locked, $actor, 'TARGET_RESUBMITTED', $from, MeetingRequestStatus::RESUBMITTED_TO_TARGET->value);
            $this->assertTransition($locked, MeetingRequestStatus::UNDER_TARGET_REVIEW);
            $from = $this->updateStatus($locked, MeetingRequestStatus::UNDER_TARGET_REVIEW);
            $this->record($locked, $actor, 'TARGET_REVIEW_STARTED', $from, MeetingRequestStatus::UNDER_TARGET_REVIEW->value);
            $this->notifyUnit($this->unitFor($locked, 'TARGET')->id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'], 'درخواست دوباره به ریاست مقصد ارسال شد', 'درخواست اصلاح‌شده برای بررسی مجدد آماده است.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
