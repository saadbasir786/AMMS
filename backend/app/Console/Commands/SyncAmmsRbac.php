<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AmmsRbacSynchronizer;
use Illuminate\Console\Command;

final class SyncAmmsRbac extends Command
{
    protected $signature = 'amms:sync-rbac';

    protected $description = 'Synchronize the current AMMS roles and permissions';

    public function handle(AmmsRbacSynchronizer $synchronizer): int
    {
        $result = $synchronizer->synchronize();
        $this->info("Synchronized {$result['roles']} roles and {$result['permissions']} permissions.");
        $this->line("Created roles: {$result['roles_created']}.");
        if ($result['unknown_system_permissions'] !== []) {
            $this->warn('Unknown permissions on canonical roles were preserved and reported: '.json_encode($result['unknown_system_permissions'], JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
