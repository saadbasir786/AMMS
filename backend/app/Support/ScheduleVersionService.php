<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingScheduleVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ScheduleVersionService
{
    public const TIMEZONE = 'Asia/Kabul';

    public function current(Meeting $meeting): MeetingScheduleVersion
    {
        $version = $meeting->relationLoaded('currentScheduleVersion')
            ? $meeting->currentScheduleVersion
            : $meeting->currentScheduleVersion()->first();
        if (!$version) throw new HttpException(422, 'SCHEDULE_VERSION_NOT_INITIALIZED');
        return $version;
    }

    public function normalize(array $data): array
    {
        $date = (string) ($data['meeting_date'] ?? '');
        $start = (string) ($data['start_time'] ?? '');
        $end = (string) ($data['end_time'] ?? '');
        $location = trim((string) ($data['location'] ?? ''));
        try {
            $startAt = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$start}", self::TIMEZONE);
            $endAt = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$date} {$end}", self::TIMEZONE);
        } catch (\Throwable) {
            throw new HttpException(422, 'INVALID_SCHEDULE_TIME');
        }
        if (!$startAt || !$endAt || $endAt->lessThanOrEqualTo($startAt)) throw new HttpException(422, 'INVALID_SCHEDULE_TIME');
        if ($startAt->lessThanOrEqualTo(CarbonImmutable::now(self::TIMEZONE))) throw new HttpException(422, 'INVALID_SCHEDULE_TIME');
        if ($location === '') throw new HttpException(422, 'INVALID_SCHEDULE_LOCATION');
        return ['meeting_date' => $date, 'start_time' => $start, 'end_time' => $end, 'location' => $location];
    }

    public function sameAs(MeetingScheduleVersion $current, array $schedule): bool
    {
        return $current->meeting_date?->toDateString() === $schedule['meeting_date']
            && substr((string) $current->start_time, 0, 5) === substr($schedule['start_time'], 0, 5)
            && substr((string) $current->end_time, 0, 5) === substr($schedule['end_time'], 0, 5)
            && trim((string) $current->location) === trim($schedule['location']);
    }

    public function createNext(Meeting $meeting, array $schedule, int $createdBy, ?MeetingRescheduleCase $case = null): MeetingScheduleVersion
    {
        $next = ((int) $meeting->scheduleVersions()->max('version_number')) + 1;
        return MeetingScheduleVersion::create([
            'meeting_id' => $meeting->id,
            'version_number' => $next,
            ...$schedule,
            'source_reschedule_case_id' => $case?->id,
            'created_by_user_id' => $createdBy,
            'created_at' => now(),
        ]);
    }

    public function applyToMeeting(Meeting $meeting, MeetingScheduleVersion $version): void
    {
        $meeting->update([
            'meeting_date' => $version->meeting_date,
            'start_time' => $version->start_time,
            'end_time' => $version->end_time,
            'location' => $version->location,
            'current_schedule_version_id' => $version->id,
        ]);
    }

    public function snapshot(MeetingScheduleVersion $version): array
    {
        return [
            'schedule_version_id' => $version->id,
            'version_number' => $version->version_number,
            'meeting_date' => $version->meeting_date?->toDateString(),
            'start_time' => $version->start_time,
            'end_time' => $version->end_time,
            'location' => $version->location,
        ];
    }
}
