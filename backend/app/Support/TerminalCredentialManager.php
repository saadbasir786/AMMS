<?php

namespace App\Support;

use App\Models\Terminal;
use App\Models\TerminalCredential;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TerminalCredentialManager
{
    public function issue(Terminal $terminal, User $actor): array
    {
        $raw = $this->newSecret();
        $credential = $terminal->credentials()->create([
            'token_hash' => $this->hash($raw),
            'issued_at' => now(),
            'created_by_user_id' => $actor->id,
        ]);

        return ['credential' => $credential, 'token' => $raw];
    }

    public function rotate(Terminal $terminal, User $actor): array
    {
        return DB::transaction(function () use ($terminal, $actor): array {
            $terminal->lockForUpdate()->findOrFail($terminal->id);
            $terminal->credentials()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            return $this->issue($terminal->fresh(), $actor);
        });
    }

    public function revokeAll(Terminal $terminal): int
    {
        return $terminal->credentials()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public function newSecret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function resolve(string $secret): ?TerminalCredential
    {
        $credential = TerminalCredential::query()
            ->with('terminal')
            ->where('token_hash', $this->hash($secret))
            ->whereNull('revoked_at')
            ->first();

        if (!$credential || !$credential->terminal || !$credential->isUsable() || $credential->terminal->status !== 'ACTIVE') {
            return null;
        }

        return $credential;
    }

    public function touch(TerminalCredential $credential): void
    {
        $now = now();
        $credential->forceFill(['last_used_at' => $now])->saveQuietly();
        $credential->terminal?->forceFill(['last_seen_at' => $now])->saveQuietly();
    }
}
