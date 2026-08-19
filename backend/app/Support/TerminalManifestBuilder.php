<?php

namespace App\Support;

use App\Models\TerminalMeetingSession;
use Illuminate\Support\Arr;

class TerminalManifestBuilder
{
    public function build(TerminalMeetingSession $session): array
    {
        $session->loadMissing([
            'terminal',
            'meeting',
            'scheduleVersion',
            'participantVersion.members.employee',
            'participantVersion.members.organizationalUnit',
        ]);

        $schedule = $session->scheduleVersion;
        $meeting = $session->meeting;
        $participants = $session->participantVersion->members->map(fn ($member): array => [
            'employee_id' => $member->employee_id,
            'display_name' => $member->employee?->full_name,
            'organizational_unit_id' => $member->organizational_unit_id,
            'organizational_unit_name' => $member->organizationalUnit?->name,
            'participant_role' => $member->participant_role,
        ])->values()->all();

        $manifest = [
            'session_uuid' => $session->uuid,
            'terminal_uuid' => $session->terminal->uuid,
            'meeting_id' => $meeting->id,
            'meeting_number' => $meeting->meeting_number,
            'subject' => $meeting->title,
            'schedule_version_id' => $session->schedule_version_id,
            'participant_version_id' => $session->participant_version_id,
            'meeting_date' => $schedule?->meeting_date?->toDateString(),
            'start_time' => $schedule?->start_time,
            'end_time' => $schedule?->end_time,
            'location' => $schedule?->location,
            'participants' => $participants,
        ];

        $canonical = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        return [...$manifest, 'manifest_checksum' => hash('sha256', $canonical)];
    }
}
