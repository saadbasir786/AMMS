<?php

namespace App\Actions\Terminals;

use App\Models\Meeting;
use App\Models\MeetingTerminalAssignment;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;

class CloseTerminalMeetingSession
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function execute(TerminalMeetingSession $session, string $reason): TerminalMeetingSession
    {
        return DB::transaction(function () use ($session, $reason): TerminalMeetingSession {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($session->meeting_id);
            $terminal = Terminal::query()->lockForUpdate()->findOrFail($session->terminal_id);
            $assignment = MeetingTerminalAssignment::query()->lockForUpdate()->findOrFail($session->assignment_id);
            $locked = TerminalMeetingSession::query()->lockForUpdate()->findOrFail($session->id);
            if ((int) $locked->terminal_id !== (int) $terminal->id || (int) $locked->meeting_id !== (int) $meeting->id) abort(404, 'TERMINAL_SESSION_NOT_FOUND');
            if ($locked->status !== 'ACTIVE') abort(422, 'TERMINAL_SESSION_INACTIVE');

            $locked->forceFill(['status' => 'CLOSED', 'closed_at' => now(), 'close_reason' => $reason])->save();
            if ((int) $terminal->current_session_id === (int) $locked->id) $terminal->forceFill(['current_session_id' => null])->save();
            if ((int) $meeting->current_terminal_session_id === (int) $locked->id) $meeting->forceFill(['current_terminal_session_id' => null])->save();
            $this->audit->record('TERMINAL_SESSION_CLOSED', null, $terminal, $meeting, $assignment, $locked, ['reason' => $reason]);

            return $locked->fresh(['terminal', 'assignment', 'meeting']);
        });
    }
}
