<?php

declare(strict_types=1);

namespace App\Actions\Minutes;

use App\Models\MeetingMinuteReview;
use App\Models\MeetingMinutes;
use App\Models\User;
use App\Support\MinutesGovernanceResolver;
use App\Support\MinutesReviewRequirementResolver;
use Illuminate\Support\Facades\DB;

class AcknowledgeMeetingMinutes
{
    public function __construct(private MinutesGovernanceResolver $governance, private MinutesReviewRequirementResolver $requirements) {}

    public function execute(User $actor, MeetingMinutes $minutes, int $unitId): MeetingMinutes
    {
        return DB::transaction(function () use ($actor, $minutes, $unitId): MeetingMinutes {
            $locked = MeetingMinutes::query()->with(['meeting.request.authorityContext', 'meeting.authorityContext'])->lockForUpdate()->findOrFail($minutes->id);
            if ($locked->status !== MeetingMinutes::UNDER_REVIEW) abort(422, 'MINUTES_REVIEW_INCOMPLETE');
            if (!$this->governance->mayReviewUnit($locked->meeting, $actor, $unitId)) abort(403, 'UNAUTHORIZED_MINUTES_REVIEW');
            $version = $locked->currentVersion()->lockForUpdate()->first();
            if (!$version || $version->submitted_at === null) abort(422, 'MINUTES_VERSION_STALE');
            $review = MeetingMinuteReview::query()->lockForUpdate()->where('meeting_minute_version_id', $version->id)->where('reviewer_type', MeetingMinuteReview::UNIT)->where('organizational_unit_id', $unitId)->firstOrFail();
            if ($review->status === MeetingMinuteReview::CORRECTION_REQUESTED) abort(422, 'MINUTES_REVIEW_INCOMPLETE');
            if ($review->status === MeetingMinuteReview::ACKNOWLEDGED) return $locked->fresh(['currentVersion.reviews']);
            $review->forceFill(['status' => MeetingMinuteReview::ACKNOWLEDGED, 'reviewed_by_user_id' => $actor->id, 'reviewed_at' => now()])->saveQuietly();
            $this->refreshReadiness($locked, $version->id);
            \App\Models\MeetingAction::create([
                'meeting_id' => $locked->meeting_id,
                'actor_id' => $actor->id,
                'action' => 'MINUTES_UNIT_ACKNOWLEDGED',
                'metadata' => ['minutes_id' => $locked->id, 'version_id' => $version->id, 'organizational_unit_id' => $unitId],
            ]);
            return $locked->fresh(['currentVersion.reviews']);
        });
    }

    private function refreshReadiness(MeetingMinutes $minutes, int $versionId): void
    {
        $reviews = MeetingMinuteReview::query()->where('meeting_minute_version_id', $versionId)->get();
        $unitsReady = $reviews->where('reviewer_type', MeetingMinuteReview::UNIT)->every(fn ($review): bool => $review->status === MeetingMinuteReview::ACKNOWLEDGED);
        $authorityReady = !$reviews->contains('reviewer_type', MeetingMinuteReview::HIGH_AUTHORITY)
            || $reviews->where('reviewer_type', MeetingMinuteReview::HIGH_AUTHORITY)->every(fn ($review): bool => $review->status === MeetingMinuteReview::APPROVED);
        if ($unitsReady && $authorityReady) $minutes->forceFill(['status' => MeetingMinutes::READY_TO_FINALIZE])->saveQuietly();
    }
}
