<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'decision.followup.view',
            'decision.followup.initialize',
            'decision.followup.classify',
            'action-item.create',
            'action-item.view',
            'action-item.start',
            'action-item.progress',
            'action-item.block',
            'action-item.submit-completion',
            'action-item.verify-completion',
            'action-item.assign',
            'action-item.change-unit',
            'action-item.extension.request',
            'action-item.extension.review',
            'action-item.cancel',
            'action-item.evidence.view',
            'action-item.evidence.upload',
        ];
        foreach ($permissions as $permission) Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);

        $rolePermissions = [
            'MEETING_COORDINATOR' => [
                'decision.followup.view', 'decision.followup.initialize', 'decision.followup.classify',
                'action-item.create', 'action-item.view', 'action-item.assign', 'action-item.change-unit',
                'action-item.extension.review', 'action-item.verify-completion', 'action-item.cancel',
                'action-item.evidence.view', 'action-item.evidence.upload',
            ],
            'EXECUTIVE_SECRETARY' => [
                'decision.followup.view', 'decision.followup.initialize', 'decision.followup.classify',
                'action-item.create', 'action-item.view', 'action-item.assign', 'action-item.change-unit',
                'action-item.extension.review', 'action-item.verify-completion', 'action-item.cancel',
                'action-item.evidence.view', 'action-item.evidence.upload',
            ],
            'DIRECTOR' => [
                'decision.followup.view', 'action-item.view', 'action-item.start', 'action-item.progress',
                'action-item.block', 'action-item.submit-completion', 'action-item.assign',
                'action-item.extension.request', 'action-item.evidence.view', 'action-item.evidence.upload',
            ],
            'HIGH_AUTHORITY' => ['decision.followup.view', 'action-item.view', 'action-item.evidence.view'],
            'AUTHORITY_OFFICE' => ['decision.followup.view', 'action-item.view', 'action-item.evidence.view'],
        ];
        foreach ($rolePermissions as $roleName => $names) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'sanctum']);
            $role->givePermissionTo($names);
        }
    }

    public function down(): void
    {
        // FLOW-12 permissions remain for audit-safe rollback.
    }
};
