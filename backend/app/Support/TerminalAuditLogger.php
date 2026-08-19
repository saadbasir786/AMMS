<?php

namespace App\Support;

use App\Models\TerminalAuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class TerminalAuditLogger
{
    public function record(
        string $action,
        ?User $actor = null,
        ?Model $terminal = null,
        ?Model $meeting = null,
        ?Model $assignment = null,
        ?Model $session = null,
        array $metadata = [],
    ): TerminalAuditEvent {
        unset($metadata['token'], $metadata['raw_token'], $metadata['session_token'], $metadata['raw_session_token']);

        return TerminalAuditEvent::create([
            'terminal_id' => $terminal?->getKey(),
            'meeting_id' => $meeting?->getKey(),
            'assignment_id' => $assignment?->getKey(),
            'session_id' => $session?->getKey(),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
