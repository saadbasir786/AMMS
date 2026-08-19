<?php

namespace App\Actions\Terminals;

use App\Models\Terminal;
use App\Models\User;
use App\Support\TerminalAuditLogger;
use App\Support\TerminalCredentialManager;
use Illuminate\Support\Facades\DB;

class RotateTerminalCredential
{
    public function __construct(
        private TerminalCredentialManager $manager,
        private TerminalAuditLogger $audit,
    ) {}

    public function execute(User $actor, Terminal $terminal): array
    {
        return DB::transaction(function () use ($actor, $terminal): array {
            $locked = Terminal::query()->lockForUpdate()->findOrFail($terminal->id);
            $previous = $locked->credentials()->whereNull('revoked_at')->pluck('id')->all();
            $issued = $this->manager->rotate($locked, $actor);
            $this->audit->record('TERMINAL_CREDENTIAL_ROTATED', $actor, $locked, metadata: [
                'terminal_uuid' => $locked->uuid,
                'revoked_credential_ids' => $previous,
                'credential_id' => $issued['credential']->id,
            ]);
            return $issued;
        });
    }
}
