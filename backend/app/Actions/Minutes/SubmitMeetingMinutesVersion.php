<?php

declare(strict_types=1);

namespace App\Actions\Minutes;

use App\Models\MeetingMinuteReview;
use App\Models\MeetingMinutes;
use App\Models\User;
use App\Support\MinutesReviewRequirementResolver;
use Illuminate\Support\Facades\DB;

class SubmitMeetingMinutesVersion
{
    public function __construct(private MinutesReviewRequirementResolver $requirements) {}

    public function execute(User $actor, MeetingMinutes $minutes): MeetingMinutes
    {
        return DB::transaction(function () use ($actor, $minutes): MeetingMinutes {
            $locked = MeetingMinutes::query()->with(['meeting.request.authorityContext', 'meeting.authorityContext'])->lockForUpdate()->findOrFail($minutes->id);
            if ($locked->status === MeetingMinutes::FINALIZED) abort(422, 'MINUTES_ALREADY_FINALIZED');
            if ($locked->meeting->status !== 'HELD') abort(422, 'MINUTES_MEETING_NOT_HELD');
            $version = $locked->current_version_id ? $locked->currentVersion()->lockForUpdate()->first() : null;
            if (!$version) abort(422, 'MINUTES_VERSION_STALE');
            if ($version->submitted_at !== null) abort(422, 'MINUTES_VERSION_STALE');
            $version->forceFill(['submitted_at' => now()])->saveQuietly();

            $requirements = $this->requirements->resolve($locked->meeting);
            foreach ($requirements['unit_ids'] as $unitId) {
                MeetingMinuteReview::create([
                    'meeting_minute_version_id' => $version->id,
                    'reviewer_type' => MeetingMinuteReview::UNIT,
                    'organizational_unit_id' => $unitId,
                    'status' => MeetingMinuteReview::PENDING,
                ]);
            }
            if ($requirements['requires_high_authority']) {
                MeetingMinuteReview::create([
                    'meeting_minute_version_id' => $version->id,
                    'reviewer_type' => MeetingMinuteReview::HIGH_AUTHORITY,
                    'authority_employee_id' => $requirements['authority_employee_id'],
                    'status' => MeetingMinuteReview::PENDING,
                ]);
            }
            $locked->forceFill(['status' => MeetingMinutes::UNDER_REVIEW])->saveQuietly();
            \App\Models\MeetingAction::create([
                'meeting_id' => $locked->meeting_id,
                'actor_id' => $actor->id,
                'action' => 'MINUTES_VERSION_SUBMITTED',
                'metadata' => ['minutes_id' => $locked->id, 'version_id' => $version->id, 'version_number' => $version->version_number],
            ]);
            return $locked->fresh(['meeting', 'currentVersion.items', 'currentVersion.decisions', 'currentVersion.attendanceSnapshots', 'currentVersion.reviews']);
        });
    }
}
