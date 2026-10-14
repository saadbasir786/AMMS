<?php

namespace App\Actions\Terminals;

use App\Models\Terminal;
use App\Models\TerminalPairingCode;
use App\Support\TerminalAuditLogger;
use App\Support\TerminalCredentialManager;
use Illuminate\Support\Facades\DB;

class PairTerminalWithCode
{
    public function __construct(
        private TerminalCredentialManager $credentials,
        private TerminalAuditLogger $audit,
    ) {}

    public function execute(string $code): ?array
    {
        return DB::transaction(function () use ($code): ?array {
            $hash = hash('sha256', $code);
            $candidate = TerminalPairingCode::query()->where('code_hash', $hash)->first();
            if (!$candidate) return null;

            // Keep the terminal-first lock order used by other terminal lifecycle actions.
            $terminal = Terminal::query()->lockForUpdate()->find($candidate->terminal_id);
            $pairingCode = TerminalPairingCode::query()->lockForUpdate()->find($candidate->id);

            if (!$terminal || !$pairingCode
                || (int) $pairingCode->terminal_id !== (int) $terminal->id
                || $pairingCode->consumed_at !== null
                || !$pairingCode->expires_at->isFuture()
                || $terminal->status !== 'ACTIVE') {
                return null;
            }

            $pairingCode->forceFill(['consumed_at' => now()])->save();
            $issuer = $pairingCode->issuedBy()->firstOrFail();
            $issued = $this->credentials->rotate($terminal, $issuer);

            $this->audit->record('TERMINAL_PAIRED', $issuer, $terminal, metadata: [
                'pairing_code_id' => $pairingCode->id,
                'credential_id' => $issued['credential']->id,
            ]);

            return [
                'terminal' => $terminal->fresh(),
                'credential' => $issued['token'],
            ];
        });
    }
}
