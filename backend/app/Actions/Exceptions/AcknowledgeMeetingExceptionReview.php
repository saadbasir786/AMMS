<?php

declare(strict_types=1);

namespace App\Actions\Exceptions;

use App\Actions\Exceptions\Concerns\ExceptionWorkflowSupport;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcknowledgeMeetingExceptionReview
{
    use ExceptionWorkflowSupport;

    public function execute(MeetingExceptionReview $review, User $actor): MeetingExceptionReview
    {
        $review->loadMissing('version.exceptionCase.meeting');
        abort_unless(app(\App\Support\MeetingExceptionGovernanceResolver::class)->mayAcknowledge($review, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');
        return DB::transaction(function () use ($review, $actor): MeetingExceptionReview {
            $locked = MeetingExceptionReview::query()->with('version.exceptionCase.meeting')->lockForUpdate()->findOrFail($review->id);
            abort_unless($locked->status === MeetingExceptionReview::PENDING, 409, 'MEETING_EXCEPTION_REVIEW_INCOMPLETE');
            $locked->forceFill(['status' => MeetingExceptionReview::ACKNOWLEDGED, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now()])->save();
            $case = MeetingExceptionCase::query()->with('currentVersion.reviews')->lockForUpdate()->findOrFail($locked->version->exception_case_id);
            $this->refreshReadiness($case);
            $this->audit($case->meeting, $actor, 'MEETING_EXCEPTION_REVIEW_ACKNOWLEDGED', null, ['exception_case_id' => $case->id, 'case_version_id' => $locked->exception_case_version_id, 'review_id' => $locked->id, 'organizational_unit_id' => $locked->organizational_unit_id]);
            return $locked->fresh(['version.exceptionCase']);
        });
    }

    private function refreshReadiness(MeetingExceptionCase $case): void
    {
        $reviews = $case->currentVersion?->reviews ?? collect();
        $complete = $reviews->isNotEmpty() && $reviews->every(fn (MeetingExceptionReview $review): bool => in_array($review->status, [MeetingExceptionReview::ACKNOWLEDGED, MeetingExceptionReview::APPROVED], true));
        if ($complete) $case->forceFill(['status' => MeetingExceptionCase::READY_TO_APPLY, 'updated_at' => now()])->save();
    }
}
