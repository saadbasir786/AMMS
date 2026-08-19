<?php

declare(strict_types=1);

namespace App\Actions\Minutes;

use App\Models\MeetingMinuteReview;
use App\Models\MeetingMinutes;
use App\Models\User;
use App\Support\MinutesGovernanceResolver;
use Illuminate\Support\Facades\DB;

class RequestMeetingMinutesCorrection
{
    public function __construct(private MinutesGovernanceResolver $governance) {}

    public function execute(User $actor, MeetingMinutes $minutes, string $reviewerType, ?int $unitId, string $reason): MeetingMinutes
    {
        return DB::transaction(function () use ($actor, $minutes, $reviewerType, $unitId, $reason): MeetingMinutes {
            $reason = trim($reason);
            if ($reason === '') abort(422, 'MINUTES_CORRECTION_REASON_REQUIRED');
            $locked = MeetingMinutes::query()->with(['meeting.request.authorityContext', 'meeting.authorityContext'])->lockForUpdate()->findOrFail($minutes->id);
            if ($locked->status !== MeetingMinutes::UNDER_REVIEW) abort(422, 'MINUTES_REVIEW_INCOMPLETE');
            $version = $locked->currentVersion()->lockForUpdate()->first();
            if (!$version || $version->submitted_at === null) abort(422, 'MINUTES_VERSION_STALE');

            $reviewerType = strtoupper($reviewerType);
            if ($reviewerType === MeetingMinuteReview::UNIT) {
                if (!$unitId || !$this->governance->mayReviewUnit($locked->meeting, $actor, $unitId)) abort(403, 'UNAUTHORIZED_MINUTES_REVIEW');
                $review = MeetingMinuteReview::query()->lockForUpdate()->where('meeting_minute_version_id', $version->id)->where('reviewer_type', MeetingMinuteReview::UNIT)->where('organizational_unit_id', $unitId)->firstOrFail();
            } elseif ($reviewerType === MeetingMinuteReview::HIGH_AUTHORITY) {
                if (!$this->governance->mayApproveHighAuthority($locked->meeting, $actor)) abort(403, 'UNAUTHORIZED_MINUTES_REVIEW');
                $review = MeetingMinuteReview::query()->lockForUpdate()->where('meeting_minute_version_id', $version->id)->where('reviewer_type', MeetingMinuteReview::HIGH_AUTHORITY)->firstOrFail();
            } else {
                abort(422, 'UNAUTHORIZED_MINUTES_REVIEW');
            }
            $review->forceFill(['status' => MeetingMinuteReview::CORRECTION_REQUESTED, 'reviewed_by_user_id' => $actor->id, 'reason' => $reason, 'reviewed_at' => now()])->saveQuietly();
            $locked->forceFill(['status' => MeetingMinutes::RETURNED_FOR_CORRECTION])->saveQuietly();
            \App\Models\MeetingAction::create([
                'meeting_id' => $locked->meeting_id,
                'actor_id' => $actor->id,
                'action' => 'MINUTES_CORRECTION_REQUESTED',
                'metadata' => ['minutes_id' => $locked->id, 'version_id' => $version->id, 'review_id' => $review->id, 'reason' => $reason],
            ]);
            return $locked->fresh(['currentVersion.reviews']);
        });
    }
}
