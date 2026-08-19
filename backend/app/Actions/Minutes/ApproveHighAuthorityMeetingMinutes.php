<?php

declare(strict_types=1);

namespace App\Actions\Minutes;

use App\Models\MeetingMinuteReview;
use App\Models\MeetingMinutes;
use App\Models\User;
use App\Support\MinutesGovernanceResolver;
use Illuminate\Support\Facades\DB;

class ApproveHighAuthorityMeetingMinutes
{
    public function __construct(private MinutesGovernanceResolver $governance) {}

    public function execute(User $actor, MeetingMinutes $minutes): MeetingMinutes
    {
        return DB::transaction(function () use ($actor, $minutes): MeetingMinutes {
            $locked = MeetingMinutes::query()->with(['meeting.request.authorityContext', 'meeting.authorityContext'])->lockForUpdate()->findOrFail($minutes->id);
            if ($locked->status !== MeetingMinutes::UNDER_REVIEW) abort(422, 'MINUTES_REVIEW_INCOMPLETE');
            if (!$this->governance->mayApproveHighAuthority($locked->meeting, $actor)) abort(403, 'UNAUTHORIZED_MINUTES_REVIEW');
            $version = $locked->currentVersion()->lockForUpdate()->first();
            if (!$version || $version->submitted_at === null) abort(422, 'MINUTES_VERSION_STALE');
            $reviews = MeetingMinuteReview::query()->where('meeting_minute_version_id', $version->id)->lockForUpdate()->get();
            if ($reviews->where('reviewer_type', MeetingMinuteReview::UNIT)->contains(fn ($review): bool => $review->status !== MeetingMinuteReview::ACKNOWLEDGED)) abort(422, 'MINUTES_ACKNOWLEDGEMENT_REQUIRED');
            $review = $reviews->first(fn ($review): bool => $review->reviewer_type === MeetingMinuteReview::HIGH_AUTHORITY && (int) $review->authority_employee_id === (int) $actor->employee_id);
            if (!$review) abort(422, 'MINUTES_HIGH_AUTHORITY_APPROVAL_REQUIRED');
            if ($review->status === MeetingMinuteReview::APPROVED) return $locked->fresh(['currentVersion.reviews']);
            $review->forceFill(['status' => MeetingMinuteReview::APPROVED, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now()])->saveQuietly();
            $locked->forceFill(['status' => MeetingMinutes::READY_TO_FINALIZE])->saveQuietly();
            \App\Models\MeetingAction::create([
                'meeting_id' => $locked->meeting_id,
                'actor_id' => $actor->id,
                'action' => 'MINUTES_HIGH_AUTHORITY_APPROVED',
                'metadata' => ['minutes_id' => $locked->id, 'version_id' => $version->id, 'authority_employee_id' => $actor->employee_id],
            ]);
            return $locked->fresh(['currentVersion.reviews']);
        });
    }
}
