<?php

namespace App\Actions\Terminals;

use App\Models\Terminal;
use App\Models\User;
use App\Support\TerminalAuditLogger;
use App\Support\TerminalCredentialManager;
use Illuminate\Support\Facades\DB;

class IssueTerminalCredential
{
    public function __construct(
        private TerminalCredentialManager $manager,
        private TerminalAuditLogger $audit,
    ) {}

    public function execute(User $actor, Terminal $terminal): array
    {
        return DB::transaction(function () use ($actor, $terminal): array {
            $locked = Terminal::query()->lockForUpdate()->findOrFail($terminal->id);
            if ($locked->credentials()->whereNull('revoked_at')->exists()) abort(422, 'TERMINAL_CREDENTIAL_ALREADY_ACTIVE');
            $issued = $this->manager->issue($locked, $actor);
            $this->audit->record('TERMINAL_CREDENTIAL_ISSUED', $actor, $locked, metadata: [
                'terminal_uuid' => $locked->uuid,
                'credential_id' => $issued['credential']->id,
            ]);
            return $issued;
        });
    }
}
