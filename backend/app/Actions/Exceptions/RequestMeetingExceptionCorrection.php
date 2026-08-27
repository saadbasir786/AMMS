<?php

declare(strict_types=1);

namespace App\Actions\Exceptions;

use App\Actions\Exceptions\Concerns\Flow13ActionSupport;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionReview;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;
use Illuminate\Support\Facades\DB;

class RequestMeetingExceptionCorrection
{
    use Flow13ActionSupport;

    public function execute(MeetingExceptionReview $review, User $actor, string $reason): MeetingExceptionCase
    {
        $review->loadMissing('version.exceptionCase.meeting');
        abort_unless(app(MeetingExceptionGovernanceResolver::class)->mayAcknowledge($review, $actor) || app(MeetingExceptionGovernanceResolver::class)->mayApprove($review, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');
        $reason = trim($reason);
        abort_unless($reason !== '', 422, 'MEETING_EXCEPTION_CORRECTION_REASON_REQUIRED');
        return DB::transaction(function () use ($review, $actor, $reason): MeetingExceptionCase {
            $lockedReview = MeetingExceptionReview::query()->with('version.exceptionCase.meeting')->lockForUpdate()->findOrFail($review->id);
            abort_unless($lockedReview->status === MeetingExceptionReview::PENDING, 409, 'MEETING_EXCEPTION_REVIEW_INCOMPLETE');
            $lockedReview->forceFill(['status' => MeetingExceptionReview::CORRECTION_REQUESTED, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now(), 'reason' => $reason])->save();
            $case = MeetingExceptionCase::query()->with('currentVersion')->lockForUpdate()->findOrFail($lockedReview->version->exception_case_id);
            $case->forceFill(['status' => MeetingExceptionCase::RETURNED_FOR_CORRECTION, 'updated_at' => now()])->save();
            $this->audit($case->meeting, $actor, 'MEETING_EXCEPTION_CASE_CORRECTION_REQUESTED', $reason, ['exception_case_id' => $case->id, 'case_version_id' => $lockedReview->exception_case_version_id, 'review_id' => $lockedReview->id]);
            return $case->fresh(['currentVersion', 'versions']);
        });
    }
}
