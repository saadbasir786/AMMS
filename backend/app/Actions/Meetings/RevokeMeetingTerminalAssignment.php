<?php

namespace App\Actions\Meetings;

use App\Models\Meeting;
use App\Models\MeetingTerminalAssignment;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;

class RevokeMeetingTerminalAssignment
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function execute(User $actor, Meeting $meeting, MeetingTerminalAssignment $assignment, string $reason): MeetingTerminalAssignment
    {
        return DB::transaction(function () use ($actor, $meeting, $assignment, $reason): MeetingTerminalAssignment {
            // FLOW-09 lock order: Terminal -> Meeting -> Assignment/Session.
            $lockedTerminal = Terminal::query()->lockForUpdate()->findOrFail($assignment->terminal_id);
            $lockedMeeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);
            $locked = MeetingTerminalAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ((int) $locked->meeting_id !== (int) $lockedMeeting->id || (int) $locked->terminal_id !== (int) $lockedTerminal->id) abort(404, 'TERMINAL_ASSIGNMENT_NOT_FOUND');
            if ($locked->status === 'REVOKED') abort(422, 'TERMINAL_ASSIGNMENT_ALREADY_REVOKED');

            $sessions = TerminalMeetingSession::query()
                ->where('assignment_id', $locked->id)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->get();
            foreach ($sessions as $session) {
                $session->forceFill(['status' => 'REVOKED', 'closed_at' => now(), 'close_reason' => $reason])->save();
                if ((int) $lockedTerminal->current_session_id === (int) $session->id) $lockedTerminal->forceFill(['current_session_id' => null])->save();
                if ((int) $lockedMeeting->current_terminal_session_id === (int) $session->id) $lockedMeeting->forceFill(['current_terminal_session_id' => null])->save();
                $this->audit->record('TERMINAL_SESSION_REVOKED', $actor, $lockedTerminal, $lockedMeeting, $locked, $session, ['reason' => $reason]);
            }

            $locked->forceFill([
                'status' => 'REVOKED',
                'revoked_by_user_id' => $actor->id,
                'revoked_at' => now(),
                'revoke_reason' => $reason,
            ])->save();
            $this->audit->record('TERMINAL_ASSIGNMENT_REVOKED', $actor, $lockedTerminal, $lockedMeeting, $locked, metadata: [
                'reason' => $reason,
            ]);

            return $locked->fresh(['terminal', 'meeting', 'baselineScheduleVersion', 'baselineParticipantVersion']);
        });
    }
}
