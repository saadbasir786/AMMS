<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\MultiDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResubmitMultiDirectorateRequest
{
    use MultiDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockFlow03Request($request);
            $this->assertActiveActor($actor);
            $requester = $this->requesterUnit($locked);
            abort_unless($actor->can('meeting-request.resubmit') && $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $requester->id), 403, 'FORBIDDEN');

            if ($locked->status === MeetingRequestStatus::RETURNED_TO_REQUESTER->value) {
                $from = $this->updateStatus($locked, MeetingRequestStatus::PENDING_REQUESTER_APPROVAL);
                $this->record($locked, $actor, 'REQUESTER_RESUBMITTED', $from, $locked->status, null, ['unit_id' => $requester->id]);
                $this->notifyUnit($requester->id, ['DIRECTOR'], 'درخواست چندریاستی دوباره برای تأیید آماده است', 'درخواست اصلاح‌شدهٔ چندریاستی منتظر تأیید ریاست شما است.', $locked);
                return $this->responseRequest($locked);
            }

            abort_unless($locked->status === MeetingRequestStatus::TARGET_ACTION_REQUIRED->value, 422, 'REQUEST_NOT_AVAILABLE_FOR_RESUBMISSION');
            $returned = $locked->unitReviews->where('review_status', 'RETURNED_FOR_CLARIFICATION');
            abort_unless($returned->isNotEmpty(), 422, 'TARGET_ACTION_NOT_REQUIRED');

            $from = $this->updateStatus($locked, MeetingRequestStatus::UNDER_MULTI_TARGET_REVIEW);
            foreach ($returned as $review) {
                $review->update(['review_status' => 'PENDING_REVIEW', 'reviewed_by' => null, 'reviewed_at' => null, 'comment' => null]);
                $this->record($locked, $actor, 'TARGET_UNIT_RESUBMITTED', $from, $locked->status, null, ['unit_id' => $review->organizational_unit_id]);
                $this->notifyUnit($review->organizational_unit_id, ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'], 'درخواست اصلاح‌شدهٔ چندریاستی', 'درخواست اصلاح‌شده برای بررسی مجدد ریاست شما آماده است.', $locked);
            }

            return $this->responseRequest($locked);
        });
    }
}
