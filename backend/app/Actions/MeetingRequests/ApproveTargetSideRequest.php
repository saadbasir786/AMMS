<?php

namespace App\Actions\MeetingRequests;

use App\Actions\MeetingRequests\Concerns\InterDirectorateWorkflowSupport;
use App\Enums\MeetingRequestStatus;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveTargetSideRequest
{
    use InterDirectorateWorkflowSupport;

    public function __invoke(User $actor, MeetingRequest $request): MeetingRequest
    {
        return DB::transaction(function () use ($actor, $request): MeetingRequest {
            $this->assertActiveActor($actor);
            abort_unless($actor->can('meeting-request.approve-target'), 403, 'FORBIDDEN');
            $locked = $this->lockFlow02Request($request);
            $requester = $this->unitFor($locked, 'REQUESTER');
            $target = $this->unitFor($locked, 'TARGET');
            abort_unless($actor->hasScopedRole('DIRECTOR', $target->id), 403, 'UNAUTHORIZED_TARGET_APPROVER');
            abort_unless(MeetingRequestStatus::tryFrom($locked->status) === MeetingRequestStatus::UNDER_TARGET_REVIEW, 422, 'REQUEST_NOT_AVAILABLE_TO_TARGET');
            abort_unless($locked->requester_approved_by && $locked->requester_approved_at, 422, 'REQUESTER_APPROVAL_REQUIRED');
            $from = $this->updateStatus($locked, MeetingRequestStatus::TARGET_APPROVED);
            $locked->update(['target_approved_by' => $actor->id, 'target_approved_at' => now()]);
            $this->record($locked, $actor, 'TARGET_APPROVED', $from, MeetingRequestStatus::TARGET_APPROVED->value);
            $this->assertTransition($locked, MeetingRequestStatus::BILATERAL_APPROVED);
            $from = $this->updateStatus($locked, MeetingRequestStatus::BILATERAL_APPROVED);
            $this->record($locked, $actor, 'BILATERAL_APPROVED', $from, MeetingRequestStatus::BILATERAL_APPROVED->value);
            $this->notifyUser($locked->lead_coordinator_user_id ?: $locked->created_by, 'تأیید دوطرفه تکمیل شد', 'هر دو ریاست با برگزاری جلسه موافقت کردند؛ زمان‌بندی می‌تواند آغاز شود.', $locked);
            $this->notifyUnit($requester->id, ['EXECUTIVE_MANAGEMENT'], 'تأیید ریاست مقصد ثبت شد', 'درخواست شما پس از تأیید هر دو ریاست آمادهٔ زمان‌بندی است.', $locked);
            $this->notifyUnit($target->id, ['EXECUTIVE_MANAGEMENT'], 'تأیید ریاست مقصد ثبت شد', 'درخواست بین‌ریاستی به مرحلهٔ زمان‌بندی رسید.', $locked);
            return $this->responseRequest($locked);
        });
    }
}
