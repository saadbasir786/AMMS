<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\HighAuthorityWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResubmitHighAuthorityMeetingRequest
{
    use HighAuthorityWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $locked = $this->lockHighAuthorityRequest($request);
            $this->assertActiveActor($actor);
            abort_unless($locked->created_by === $actor->id && $this->canManageRequester($actor, $locked->requesting_unit_id), 403, 'FORBIDDEN');

            $from = $locked->status;
            if ($from === MeetingRequestStatus::RETURNED_TO_REQUESTER->value) {
                $to = MeetingRequestStatus::PENDING_REQUESTER_APPROVAL;
            } elseif (in_array($from, [MeetingRequestStatus::RETURNED_BY_AUTHORITY_OFFICE->value, MeetingRequestStatus::RETURNED_BY_HIGH_AUTHORITY->value], true)) {
                $to = MeetingRequestStatus::SUBMITTED_TO_AUTHORITY_OFFICE;
            } else {
                abort(422, 'REQUEST_NOT_RESUBMITTABLE');
            }

            $this->updateStatus($locked, $to);
            if ($to === MeetingRequestStatus::SUBMITTED_TO_AUTHORITY_OFFICE) {
                $locked->authorityContext()->update(['office_review_status' => 'PENDING', 'office_reviewed_by' => null, 'office_reviewed_at' => null]);
            }
            $this->record($locked, $actor, $to === MeetingRequestStatus::SUBMITTED_TO_AUTHORITY_OFFICE->value ? 'AUTHORITY_OFFICE_RESUBMITTED' : 'REQUESTER_RESUBMITTED', $from, $locked->status);
            if ($to === MeetingRequestStatus::SUBMITTED_TO_AUTHORITY_OFFICE) {
                $officeId = $locked->authorityContext?->authority_office_unit_id;
                if ($officeId) $this->notifyUsersForUnit($officeId, ['AUTHORITY_OFFICE'], 'درخواست اصلاح‌شده در دفتر مقام', 'نسخهٔ اصلاح‌شدهٔ درخواست برای بررسی دوباره ارسال شد.', $locked);
            }
            return $this->responseRequest($locked);
        });
    }
}
