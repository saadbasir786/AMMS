<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\MeetingTerminalAssignment;
use App\Models\Terminal;
use App\Support\MeetingOrganizationResolver;
use Carbon\CarbonImmutable;

class TerminalAssignmentConflictGuard
{
    public const TIMEZONE = 'Asia/Kabul';

    public function assertAssignable(Terminal $terminal, Meeting $meeting, CarbonImmutable $validFrom, CarbonImmutable $validUntil, ?MeetingTerminalAssignment $ignore = null): void
    {
        if ($terminal->status !== 'ACTIVE') abort(422, 'TERMINAL_NOT_AVAILABLE');
        if ($meeting->status !== 'SCHEDULED') abort(422, 'MEETING_NOT_SCHEDULED');
        if (!$meeting->current_schedule_version_id || !$meeting->current_participant_version_id) abort(422, 'MEETING_VERSIONS_REQUIRED');

        $meetingOrganizationId = app(MeetingOrganizationResolver::class)->id($meeting);
        if ((int) $meetingOrganizationId !== (int) $terminal->organization_id) {
            abort(422, 'TERMINAL_OUTSIDE_MEETING_ORGANIZATION');
        }

        if ($validFrom->greaterThanOrEqualTo($validUntil)) abort(422, 'INVALID_TERMINAL_ASSIGNMENT_WINDOW');

        [$meetingStart, $meetingEnd] = $this->meetingInterval($meeting);
        if ($validFrom->greaterThan($meetingStart) || $validUntil->lessThan($meetingEnd)) {
            abort(422, 'TERMINAL_ASSIGNMENT_WINDOW_NOT_COVERING_MEETING');
        }

        $activeForMeeting = MeetingTerminalAssignment::query()
            ->where('meeting_id', $meeting->id)
            ->where('terminal_id', $terminal->id)
            ->whereIn('status', ['ASSIGNED', 'STALE'])
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->exists();
        if ($activeForMeeting) abort(422, 'TERMINAL_ASSIGNMENT_EXISTS');

        $conflict = MeetingTerminalAssignment::query()
            ->where('terminal_id', $terminal->id)
            ->whereIn('status', ['ASSIGNED', 'STALE'])
            ->where('valid_from', '<', $validUntil->toDateTimeString())
            ->where('valid_until', '>', $validFrom->toDateTimeString())
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->orderBy('valid_from')
            ->first();

        if ($conflict) {
            abort(response()->json([
                'success' => false,
                'code' => 'TERMINAL_ASSIGNMENT_CONFLICT',
                'message' => 'پنجرهٔ تخصیص ترمینال با تخصیص موجود تداخل دارد.',
                'errors' => [
                    'terminal_uuid' => [$terminal->uuid],
                    'conflicting_window_start' => [$conflict->valid_from?->toIso8601String()],
                    'conflicting_window_end' => [$conflict->valid_until?->toIso8601String()],
                ],
            ], 422));
        }
    }

    public function parseWindow(string $validFrom, string $validUntil): array
    {
        return [
            CarbonImmutable::parse($validFrom, self::TIMEZONE)->utc(),
            CarbonImmutable::parse($validUntil, self::TIMEZONE)->utc(),
        ];
    }

    public function meetingInterval(Meeting $meeting): array
    {
        $schedule = $meeting->relationLoaded('currentScheduleVersion') ? $meeting->currentScheduleVersion : $meeting->currentScheduleVersion()->first();
        $date = $schedule?->meeting_date?->toDateString() ?? $meeting->meeting_date?->toDateString() ?? (string) $meeting->meeting_date;
        return [
            CarbonImmutable::parse($date.' '.($schedule?->start_time ?? $meeting->start_time), self::TIMEZONE)->utc(),
            CarbonImmutable::parse($date.' '.($schedule?->end_time ?? $meeting->end_time), self::TIMEZONE)->utc(),
        ];
    }
}
