<?php

declare(strict_types=1);

namespace App\Actions\Minutes;

use App\Models\Meeting;
use App\Models\MeetingMinuteReview;
use App\Models\MeetingMinutes;
use App\Models\User;
use App\Support\MinutesGovernanceResolver;
use Illuminate\Support\Facades\DB;

class FinalizeMeetingMinutes
{
    public function __construct(private MinutesGovernanceResolver $governance) {}

    public function execute(User $actor, MeetingMinutes $minutes): MeetingMinutes
    {
        return DB::transaction(function () use ($actor, $minutes): MeetingMinutes {
            $root = MeetingMinutes::query()->findOrFail($minutes->id);
            $meeting = Meeting::query()->with(['request.authorityContext', 'authorityContext'])->lockForUpdate()->findOrFail($root->meeting_id);
            $locked = MeetingMinutes::query()->lockForUpdate()->findOrFail($root->id);
            if ($locked->status === MeetingMinutes::FINALIZED || $meeting->status === 'COMPLETED') abort(422, 'MINUTES_ALREADY_FINALIZED');
            if (!$this->governance->mayFinalize($meeting, $actor)) abort(403, 'UNAUTHORIZED_MINUTES_FINALIZATION');
            if ($meeting->status !== 'HELD') abort(422, 'MINUTES_MEETING_NOT_HELD');
            $version = $locked->currentVersion()->lockForUpdate()->first();
            if (!$version || $version->submitted_at === null) abort(422, 'MINUTES_VERSION_STALE');
            $reviews = MeetingMinuteReview::query()->where('meeting_minute_version_id', $version->id)->lockForUpdate()->get();
            if ($reviews->where('reviewer_type', MeetingMinuteReview::UNIT)->contains(fn ($review): bool => $review->status !== MeetingMinuteReview::ACKNOWLEDGED)) abort(422, 'MINUTES_REVIEW_INCOMPLETE');
            if ($reviews->where('reviewer_type', MeetingMinuteReview::HIGH_AUTHORITY)->contains(fn ($review): bool => $review->status !== MeetingMinuteReview::APPROVED)) abort(422, 'MINUTES_HIGH_AUTHORITY_APPROVAL_REQUIRED');
            if ($locked->status !== MeetingMinutes::READY_TO_FINALIZE) abort(422, 'MINUTES_REVIEW_INCOMPLETE');

            $now = now();
            $locked->forceFill(['status' => MeetingMinutes::FINALIZED, 'finalized_at' => $now, 'finalized_by_user_id' => $actor->id])->saveQuietly();
            $meeting->forceFill(['status' => 'COMPLETED'])->saveQuietly();
            \App\Models\MeetingAction::create([
                'meeting_id' => $meeting->id,
                'actor_id' => $actor->id,
                'action' => 'MINUTES_FINALIZED',
                'from_status' => MeetingMinutes::READY_TO_FINALIZE,
                'to_status' => MeetingMinutes::FINALIZED,
                'metadata' => ['minutes_id' => $locked->id, 'version_id' => $version->id],
            ]);
            \App\Models\MeetingAction::create([
                'meeting_id' => $meeting->id,
                'actor_id' => $actor->id,
                'action' => 'MEETING_COMPLETED',
                'from_status' => 'HELD',
                'to_status' => 'COMPLETED',
                'metadata' => ['minutes_id' => $locked->id, 'version_id' => $version->id],
            ]);
            return $locked->fresh(['meeting', 'currentVersion.items', 'currentVersion.decisions', 'currentVersion.attendanceSnapshots', 'currentVersion.reviews']);
        });
    }
}
