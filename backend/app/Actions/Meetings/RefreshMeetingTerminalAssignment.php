<?php

namespace App\Actions\Meetings;

use App\Models\Meeting;
use App\Models\MeetingTerminalAssignment;
use App\Models\Terminal;
use App\Models\User;
use App\Support\TerminalAssignmentConflictGuard;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;

class RefreshMeetingTerminalAssignment
{
    public function __construct(
        private TerminalAssignmentConflictGuard $guard,
        private TerminalAuditLogger $audit,
    ) {}

    public function execute(User $actor, Meeting $meeting, MeetingTerminalAssignment $assignment): MeetingTerminalAssignment
    {
        return DB::transaction(function () use ($actor, $meeting, $assignment): MeetingTerminalAssignment {
            $lockedMeeting = Meeting::query()
                ->with(['request', 'authorityContext', 'currentScheduleVersion', 'currentParticipantVersion'])
                ->lockForUpdate()
                ->findOrFail($meeting->id);
            $lockedTerminal = Terminal::query()->lockForUpdate()->findOrFail($assignment->terminal_id);
            $locked = MeetingTerminalAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ((int) $locked->meeting_id !== (int) $lockedMeeting->id) abort(404, 'TERMINAL_ASSIGNMENT_NOT_FOUND');
            if ($locked->status === 'REVOKED') abort(422, 'TERMINAL_ASSIGNMENT_REVOKED');
            if ($locked->status === 'EXPIRED') abort(422, 'TERMINAL_ASSIGNMENT_EXPIRED');
            if (!$locked->isStale($lockedMeeting)) abort(422, 'TERMINAL_ASSIGNMENT_NOT_STALE');

            $this->guard->assertAssignable(
                $lockedTerminal,
                $lockedMeeting,
                $locked->valid_from,
                $locked->valid_until,
                $locked,
            );

            $oldSchedule = $locked->baseline_schedule_version_id;
            $oldParticipants = $locked->baseline_participant_version_id;
            $locked->forceFill([
                'baseline_schedule_version_id' => $lockedMeeting->current_schedule_version_id,
                'baseline_participant_version_id' => $lockedMeeting->current_participant_version_id,
                'status' => 'ASSIGNED',
                'refreshed_at' => now(),
            ])->save();

            $this->audit->record('TERMINAL_ASSIGNMENT_REFRESHED', $actor, $lockedTerminal, $lockedMeeting, $locked, metadata: [
                'old_schedule_version' => $oldSchedule,
                'new_schedule_version' => $locked->baseline_schedule_version_id,
                'old_participant_version' => $oldParticipants,
                'new_participant_version' => $locked->baseline_participant_version_id,
            ]);

            return $locked->fresh(['terminal', 'meeting', 'baselineScheduleVersion', 'baselineParticipantVersion']);
        });
    }
}
