<?php

namespace App\Actions\Attendance;

use App\Models\MeetingAttendanceRecord;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordManualAttendance
{
    public function execute(User $actor, Meeting $meeting, int $employeeId, string $reason): MeetingAttendanceRecord
    {
        return DB::transaction(function () use ($actor, $meeting, $employeeId, $reason): MeetingAttendanceRecord {
            $lockedMeeting = Meeting::query()->with('currentParticipantVersion.members')->lockForUpdate()->findOrFail($meeting->id);
            $member = $lockedMeeting->currentParticipantVersion?->members->firstWhere('employee_id', $employeeId);
            if ($member === null) abort(response()->json(['success' => false, 'code' => 'ATTENDANCE_EMPLOYEE_NOT_PARTICIPANT', 'message' => 'کارمند عضو نسخهٔ رسمی اشتراک‌کنندگان نیست.', 'errors' => []], 422));
            $existing = MeetingAttendanceRecord::query()->where('meeting_id', $lockedMeeting->id)->where('employee_id', $employeeId)->lockForUpdate()->first();
            if ($existing !== null) abort(response()->json(['success' => false, 'code' => 'ATTENDANCE_ALREADY_RECORDED', 'message' => 'حضور قبلاً ثبت شده است.', 'errors' => []], 409));
            return MeetingAttendanceRecord::create([
                'uuid' => (string) Str::uuid(),
                'meeting_id' => $lockedMeeting->id,
                'employee_id' => $employeeId,
                'participant_version_id' => $lockedMeeting->current_participant_version_id,
                'attendance_method' => 'MANUAL_OVERRIDE',
                'client_event_uuid' => (string) Str::uuid(),
                'recorded_at_server' => now(),
                'recorded_by_user_id' => $actor->id,
                'manual_reason' => $reason,
            ]);
        });
    }
}
