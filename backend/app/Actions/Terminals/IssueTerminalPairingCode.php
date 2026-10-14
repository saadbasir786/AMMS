<?php

namespace App\Actions\Terminals;

use App\Models\Terminal;
use App\Models\User;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssueTerminalPairingCode
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function execute(User $actor, Terminal $terminal): array
    {
        return DB::transaction(function () use ($actor, $terminal): array {
            $locked = Terminal::query()->lockForUpdate()->findOrFail($terminal->id);
            abort_unless($locked->status === 'ACTIVE', 422, 'TERMINAL_NOT_AVAILABLE');

            // Expired codes are short-lived authentication material, not business history.
            $locked->pairingCodes()->where('expires_at', '<=', now())->delete();
            $locked->pairingCodes()->whereNull('consumed_at')->update(['consumed_at' => now()]);

            $code = null;
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $candidate = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
                if (!$locked->pairingCodes()->where('code_hash', hash('sha256', $candidate))->exists()) {
                    $code = $candidate;
                    break;
                }
            }
            abort_if($code === null, 503, 'TERMINAL_PAIRING_CODE_UNAVAILABLE');

            $pairingCode = $locked->pairingCodes()->create([
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes(3),
                'issued_by_user_id' => $actor->id,
            ]);

            $this->audit->record('TERMINAL_PAIRING_CODE_ISSUED', $actor, $locked, metadata: [
                'pairing_code_id' => $pairingCode->id,
                'expires_at' => $pairingCode->expires_at->toIso8601String(),
            ]);

            return ['code' => $code, 'expires_at' => $pairingCode->expires_at];
        });
    }
}
