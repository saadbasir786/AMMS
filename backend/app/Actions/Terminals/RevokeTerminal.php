<?php

namespace App\Actions\Terminals;

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
                $session->forceFill([
                    'status' => 'REVOKED',
                    'closed_at' => now(),
                    'close_reason' => $reason,
                ])->save();
                $this->audit->record('TERMINAL_SESSION_REVOKED', $actor, $locked, $session->meeting, $session->assignment, $session, [
                    'reason' => $reason,
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
