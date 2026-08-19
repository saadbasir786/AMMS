<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MeetingMinuteVersion;
use App\Models\MeetingMinuteAttendanceSnapshot;
use App\Models\MeetingAttendanceRecord;

class MeetingAttendanceSnapshotBuilder
{
    public function rebuild(MeetingMinuteVersion $version): void
    {
        $version->loadMissing(['minutes.meeting', 'participantVersion.members.employee.unit', 'executionSession']);
        $meeting = $version->minutes->meeting;
        $executionParticipantVersionId = $version->participant_version_id;
        $attendance = MeetingAttendanceRecord::query()
            ->where('meeting_id', $meeting->id)
            ->where('participant_version_id', $executionParticipantVersionId)
            ->get()->keyBy('employee_id');

        MeetingMinuteAttendanceSnapshot::query()->where('meeting_minute_version_id', $version->id)->delete();
        foreach ($version->participantVersion->members as $member) {
            $employee = $member->employee;
            MeetingMinuteAttendanceSnapshot::create([
                'meeting_minute_version_id' => $version->id,
                'employee_id' => $employee->id,
                'organizational_unit_id' => $member->organizational_unit_id,
                'participant_role' => $member->participant_role,
                'display_name' => (string) ($employee->full_name ?? trim($employee->first_name.' '.$employee->last_name)),
                'attendance_status' => $attendance->has($employee->id) ? 'PRESENT' : 'ABSENT',
            ]);
        }
    }
}
