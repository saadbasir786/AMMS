<?php

declare(strict_types=1);

namespace App\Actions\Flow13;

use App\Actions\Flow13\Concerns\Flow13ActionSupport;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApproveMeetingExceptionReview
{
    use Flow13ActionSupport;

    public function execute(MeetingExceptionReview $review, User $actor): MeetingExceptionReview
    {
        $review->loadMissing('version.exceptionCase.meeting');
        abort_unless(app(\App\Support\MeetingExceptionGovernanceResolver::class)->mayApprove($review, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');
        return DB::transaction(function () use ($review, $actor): MeetingExceptionReview {
            $locked = MeetingExceptionReview::query()->with('version.exceptionCase.meeting')->lockForUpdate()->findOrFail($review->id);
            abort_unless($locked->status === MeetingExceptionReview::PENDING, 409, 'MEETING_EXCEPTION_REVIEW_INCOMPLETE');
            $locked->forceFill(['status' => MeetingExceptionReview::APPROVED, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now()])->save();
            $case = MeetingExceptionCase::query()->with('currentVersion.reviews')->lockForUpdate()->findOrFail($locked->version->exception_case_id);
            $reviews = $case->currentVersion?->reviews ?? collect();
            if ($reviews->isNotEmpty() && $reviews->every(fn (MeetingExceptionReview $row): bool => in_array($row->status, [MeetingExceptionReview::ACKNOWLEDGED, MeetingExceptionReview::APPROVED], true))) {
                $case->forceFill(['status' => MeetingExceptionCase::READY_TO_APPLY, 'updated_at' => now()])->save();
            }
            $this->audit($case->meeting, $actor, 'MEETING_EXCEPTION_REVIEW_APPROVED', null, ['exception_case_id' => $case->id, 'case_version_id' => $locked->exception_case_version_id, 'review_id' => $locked->id, 'authority_employee_id' => $locked->authority_employee_id]);
            return $locked->fresh(['version.exceptionCase']);
        });
    }
}
