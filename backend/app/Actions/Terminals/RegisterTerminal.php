<?php

namespace App\Actions\Terminals;

use App\Models\Terminal;
use App\Models\User;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterTerminal
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function execute(User $actor, array $attributes): Terminal
    {
        return DB::transaction(function () use ($actor, $attributes): Terminal {
            $terminal = Terminal::create([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $attributes['organization_id'],
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'serial_number' => $attributes['serial_number'] ?? null,
                'manufacturer' => $attributes['manufacturer'] ?? null,
                'model' => $attributes['model'] ?? null,
                'platform' => $attributes['platform'] ?? null,
                'os_version' => $attributes['os_version'] ?? null,
                'app_version' => $attributes['app_version'] ?? null,
                'status' => $attributes['status'] ?? 'ACTIVE',
                'registered_by_user_id' => $actor->id,
                'registered_at' => now(),
            ]);

            $this->audit->record('TERMINAL_REGISTERED', $actor, $terminal, metadata: [
                'terminal_uuid' => $terminal->uuid,
                'organization_id' => $terminal->organization_id,
                'code' => $terminal->code,
            ]);

            return $terminal->fresh('organization');
        });
    }
}
