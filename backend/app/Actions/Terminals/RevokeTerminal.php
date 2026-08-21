<?php

namespace App\Actions\Terminals;

use App\Models\Meeting;
use App\Models\MeetingTerminalAssignment;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Support\TerminalAuditLogger;
use App\Support\TerminalCredentialManager;
use Illuminate\Support\Facades\DB;

class RevokeTerminal
{
    public function __construct(
        private TerminalCredentialManager $credentials,
        private TerminalAuditLogger $audit,
    ) {}

    public function execute(User $actor, Terminal $terminal, string $reason): Terminal
    {
        return DB::transaction(function () use ($actor, $terminal, $reason): Terminal {
            $locked = Terminal::query()->lockForUpdate()->findOrFail($terminal->id);
            $this->credentials->revokeAll($locked);

            $sessions = TerminalMeetingSession::query()
                ->where('terminal_id', $locked->id)
                ->where('status', 'ACTIVE')
                ->lockForUpdate()
                ->get();
            foreach ($sessions as $session) {
                $meeting = Meeting::query()->lockForUpdate()->findOrFail($session->meeting_id);
                $assignment = MeetingTerminalAssignment::query()->lockForUpdate()->findOrFail($session->assignment_id);

                $session->forceFill([
                    'status' => 'REVOKED',
                    'closed_at' => now(),
                    'close_reason' => $reason,
                ])->save();

                if ((int) $meeting->current_terminal_session_id === (int) $session->id) {
                    $meeting->forceFill(['current_terminal_session_id' => null])->save();
                }

                $this->audit->record('TERMINAL_SESSION_REVOKED', $actor, $locked, $meeting, $assignment, $session, [
                    'reason' => $reason,
                ]);
            }

            $assignments = MeetingTerminalAssignment::query()
                ->where('terminal_id', $locked->id)
                ->whereIn('status', ['ASSIGNED', 'STALE'])
                ->lockForUpdate()
                ->get();
            foreach ($assignments as $assignment) {
                $assignment->forceFill([
                    'status' => 'REVOKED',
                    'revoked_by_user_id' => $actor->id,
                    'revoked_at' => now(),
                    'revoke_reason' => $reason,
                ])->save();
                $this->audit->record('TERMINAL_ASSIGNMENT_REVOKED', $actor, $locked, $assignment->meeting, $assignment, metadata: [
                    'reason' => $reason,
                    'source' => 'TERMINAL_REVOKED',
                ]);
            }

            $locked->forceFill(['status' => 'REVOKED', 'current_session_id' => null])->save();
            $this->audit->record('TERMINAL_CREDENTIAL_REVOKED', $actor, $locked, metadata: [
                'terminal_uuid' => $locked->uuid,
                'reason' => $reason,
            ]);
            $this->audit->record('TERMINAL_STATUS_CHANGED', $actor, $locked, metadata: [
                'terminal_uuid' => $locked->uuid,
                'status_after' => 'REVOKED',
                'reason' => $reason,
            ]);

            return $locked->fresh('organization');
        });
    }
}
