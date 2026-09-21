<?php

namespace App\Actions\Meetings;

use App\Models\Meeting;
use App\Models\MeetingScheduleVersion;
use Illuminate\Support\Facades\DB;

class BackfillMeetingScheduleVersions
{
    public function __invoke(): array
    {
        $backfilled = [];
        $invalid = [];
        DB::transaction(function () use (&$backfilled, &$invalid): void {
            Meeting::query()->orderBy('id')->each(function (Meeting $meeting) use (&$backfilled, &$invalid): void {
                if (!$meeting->meeting_date || !$meeting->start_time || !$meeting->end_time || trim((string) $meeting->location) === '') {
                    $invalid[] = $meeting->id;
                    return;
                }
                $version = MeetingScheduleVersion::firstOrCreate([
                    'meeting_id' => $meeting->id,
                    'version_number' => 1,
                ], [
                    'meeting_date' => $meeting->meeting_date,
                    'start_time' => $meeting->start_time,
                    'end_time' => $meeting->end_time,
                    'location' => $meeting->location,
                    'venue_type' => $meeting->venue_type ?: 'EXTERNAL_LOCATION',
                    'venue_id' => $meeting->venue_id,
                    'venue_snapshot' => $meeting->venue_snapshot ?: ['type' => $meeting->venue_type ?: 'EXTERNAL_LOCATION', 'location' => $meeting->location],
                    'created_by_user_id' => $meeting->coordinator_id,
                    'created_at' => now(),
                ]);
                if (!$meeting->current_schedule_version_id) {
                    $meeting->update(['current_schedule_version_id' => $version->id]);
                    $backfilled[] = $meeting->id;
                }
            });
        });
        return ['backfilled_meeting_ids' => $backfilled, 'invalid_meeting_ids' => $invalid];
    }
}
