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
            'participantVersion.members.employee.position',
            'participantVersion.members.organizationalUnit',
            'meeting.attendanceRecords',
        ]);

        $schedule = $session->scheduleVersion;
        $meeting = $session->meeting;
        $attendanceByEmployee = $meeting->attendanceRecords->keyBy('employee_id');
        $participants = $session->participantVersion->members->map(function ($member) use ($attendanceByEmployee): array {
            $employee = $member->employee;
            $attendance = $attendanceByEmployee->get($member->employee_id);

            return [
                'employee_id' => $member->employee_id,
                'employee_number' => $employee?->employee_number,
                'first_name' => $employee?->first_name,
                'last_name' => $employee?->last_name,
                'display_name' => $employee?->full_name,
                'job_title' => $employee?->position?->title,
                'organizational_unit_id' => $member->organizational_unit_id,
                'organizational_unit_name' => $member->organizationalUnit?->name,
                'participant_role' => $member->participant_role,
                'attendance_status' => $attendance ? 'PRESENT' : 'NOT_CHECKED_IN',
            ];
        })->values()->all();

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
