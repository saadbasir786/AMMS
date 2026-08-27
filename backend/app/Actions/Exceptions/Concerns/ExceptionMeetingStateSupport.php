<?php

declare(strict_types=1);

namespace App\Actions\Exceptions\Concerns;

use App\Actions\Meetings\RevokeMeetingTerminalAssignment;
use App\Models\Meeting;
use App\Models\MeetingAttendanceInvalidation;
use App\Models\MeetingExceptionCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

trait ExceptionMeetingStateSupport
{
    protected function assertCaseReadyForApplication(MeetingExceptionCase $case): void
    {
        if ($case->status === MeetingExceptionCase::APPLIED) {
            abort(409, 'MEETING_EXCEPTION_ALREADY_APPLIED');
        }
        abort_unless($case->status === MeetingExceptionCase::READY_TO_APPLY, 409, 'MEETING_EXCEPTION_REVIEW_INCOMPLETE');
    }

    protected function assertBaseline(MeetingExceptionCase $case, Meeting $meeting): void
    {
        abort_unless($meeting->status === $case->baseline_meeting_status
            && (int) $meeting->current_schedule_version_id === (int) $case->baseline_schedule_version_id
            && (int) $meeting->current_participant_version_id === (int) $case->baseline_participant_version_id,
            409,
            'MEETING_EXCEPTION_BASELINE_CHANGED');
    }

    protected function revokeTerminalAccess(Meeting $meeting, User $actor, string $reason): void
    {
        $assignments = $meeting->terminalAssignments()->whereIn('status', ['ASSIGNED', 'ACTIVE'])->lockForUpdate()->get();
        $revoke = app(RevokeMeetingTerminalAssignment::class);
        foreach ($assignments as $assignment) {
            try {
                $revoke->execute($actor, $meeting, $assignment, $reason);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                if ($e->getMessage() !== 'TERMINAL_ASSIGNMENT_ALREADY_REVOKED') throw $e;
            }
            $this->audit($meeting, $actor, 'TERMINAL_ASSIGNMENT_REVOKED_BY_EXCEPTION', null, ['assignment_id' => $assignment->id, 'reason' => $reason]);
            $this->audit($meeting, $actor, 'TERMINAL_SESSION_REVOKED_BY_EXCEPTION', null, ['assignment_id' => $assignment->id, 'reason' => $reason]);
        }
    }

    protected function invalidateAttendance(Meeting $meeting, MeetingExceptionCase $case, User $actor, string $reason): void
    {
        $records = $meeting->attendanceRecords()->lockForUpdate()->get();
        foreach ($records as $record) {
            $created = MeetingAttendanceInvalidation::firstOrCreate(
                ['meeting_attendance_record_id' => $record->id],
                ['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'exception_case_id' => $case->id, 'reason' => $reason, 'invalidated_at' => now(), 'invalidated_by_user_id' => $actor->id, 'created_at' => now()],
            );
            if ($created->wasRecentlyCreated) {
                $this->audit($meeting, $actor, 'ATTENDANCE_INVALIDATED_BY_'.($reason === MeetingAttendanceInvalidation::POSTPONED ? 'POSTPONEMENT' : 'CANCELLATION'), null, ['attendance_record_id' => $record->id, 'exception_case_id' => $case->id, 'reason' => $reason]);
            }
        }
    }
}
