<?php

use App\Models\AmmsRoleScope;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            'meeting-request.view','meeting-request.create','meeting-request.update','meeting-request.submit','meeting-request.resubmit','meeting-request.approve','meeting-request.return','meeting-request.reject','meeting-request.attach','meeting-request.attachment-download','meeting-request.attachment-delete','meeting.schedule','meeting.create','meeting.view','notification.view','notification.mark-read','organization.manage','employee.manage','user.manage','role.manage',
        ];
        foreach ($permissions as $permission) Permission::firstOrCreate(['name'=>$permission,'guard_name'=>'sanctum']);

        $rolePermissions = [
            'SYSTEM_ADMIN' => $permissions,
            'EXECUTIVE_MANAGEMENT' => ['meeting-request.view','meeting-request.create','meeting-request.update','meeting-request.submit','meeting-request.resubmit','meeting-request.attach','meeting-request.attachment-download','meeting-request.attachment-delete','meeting.schedule','meeting.create','meeting.view','notification.view','notification.mark-read'],
            'DIRECTOR' => ['meeting-request.view','meeting-request.approve','meeting-request.return','meeting-request.reject','meeting-request.attachment-download','meeting.view','notification.view','notification.mark-read'],
            'MEETING_COORDINATOR' => ['meeting-request.view','meeting-request.attachment-download','meeting.schedule','meeting.create','meeting.view','notification.view','notification.mark-read'],
            'EMPLOYEE' => ['meeting-request.view','meeting.view','notification.view','notification.mark-read'],
        ];
        foreach ($rolePermissions as $roleName => $rolePermissionNames) {
            $role = Role::firstOrCreate(['name'=>$roleName,'guard_name'=>'sanctum']);
            $role->syncPermissions($rolePermissionNames);
        }

        $legacyCount = DB::table('user_roles')->count();
        $mappedCount = 0;
        DB::table('user_roles')->orderBy('id')->get()->each(function ($legacy) use (&$mappedCount): void {
            $user = User::find($legacy->user_id);
            $role = Role::where('name', $legacy->role)->where('guard_name', 'sanctum')->first();
            if (!$user || !$role) return;
            if (!$user->roles()->whereKey($role->id)->exists()) $user->assignRole($role);
            AmmsRoleScope::firstOrCreate(['user_id'=>$user->id,'role_id'=>$role->id,'organizational_unit_id'=>$legacy->organizational_unit_id]);
            $mappedCount++;
        });
        Log::info('AMMS Spatie RBAC backfill completed', ['legacy_assignments'=>$legacyCount,'mapped_assignments'=>$mappedCount,'roles'=>count($rolePermissions),'permissions'=>count($permissions)]);
    }

    public function down(): void
    {
        // Deliberately non-destructive: legacy user_roles and migrated Spatie data remain available for rollback/audit.
    }
};
