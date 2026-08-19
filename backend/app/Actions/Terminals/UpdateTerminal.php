<?php

namespace App\Actions\Terminals;

use App\Models\Terminal;
use App\Models\User;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;

class UpdateTerminal
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function execute(User $actor, Terminal $terminal, array $attributes): Terminal
    {
        return DB::transaction(function () use ($actor, $terminal, $attributes): Terminal {
            $locked = Terminal::query()->lockForUpdate()->findOrFail($terminal->id);
            $beforeStatus = $locked->status;
            $locked->fill(array_filter([
                'code' => $attributes['code'] ?? null,
                'name' => $attributes['name'] ?? null,
                'serial_number' => $attributes['serial_number'] ?? null,
                'manufacturer' => $attributes['manufacturer'] ?? null,
                'model' => $attributes['model'] ?? null,
                'platform' => $attributes['platform'] ?? null,
                'os_version' => $attributes['os_version'] ?? null,
                'app_version' => $attributes['app_version'] ?? null,
                'status' => $attributes['status'] ?? null,
            ], fn ($value): bool => $value !== null));
            $locked->save();

            $action = $beforeStatus !== $locked->status ? 'TERMINAL_STATUS_CHANGED' : 'TERMINAL_METADATA_UPDATED';
            $this->audit->record($action, $actor, $locked, metadata: [
                'terminal_uuid' => $locked->uuid,
                'status_before' => $beforeStatus,
                'status_after' => $locked->status,
            ]);

            return $locked->fresh('organization');
        });
    }
}
