<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Keep the persisted RBAC model aligned with the FLOW-11 authority charter.
     *
     * The former coordinator role was consolidated into EXECUTIVE_MANAGEMENT,
     * but databases migrated before that consolidation could retain an
     * incomplete permission set for the real operational role.
     */
    public function up(): void
    {
        $permissions = [
            'meeting.execution.start',
            'meeting.execution.end',
            'meeting.minutes.view',
            'meeting.minutes.prepare',
            'meeting.minutes.submit',
            'meeting.minutes.finalize',
            'meeting.minutes.document.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]);
        }

        $role = Role::firstOrCreate([
            'name' => 'EXECUTIVE_MANAGEMENT',
            'guard_name' => 'sanctum',
        ]);

        // Review and high-authority approval remain explicitly outside this role.
        $role->givePermissionTo($permissions);
    }

    public function down(): void
    {
        // Deliberately non-destructive: FLOW-11 permissions remain available for audit-safe rollback.
    }
};
