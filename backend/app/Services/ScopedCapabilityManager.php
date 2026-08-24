<?php

namespace App\Services;

use App\Models\AmmsCapabilityScope;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\SpecialCapability;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class ScopedCapabilityManager
{
    public function grant(User $user, string $capability, OrganizationalUnit $unit, ?User $grantedBy = null): AmmsCapabilityScope
    {
        $permissions = SpecialCapability::permissions($capability);
        $this->assertGrantableSubject($user, $unit);

        return DB::transaction(function () use ($user, $capability, $unit, $grantedBy, $permissions): AmmsCapabilityScope {
            $existing = AmmsCapabilityScope::query()
                ->active()
                ->where('user_id', $user->id)
                ->where('capability', $capability)
                ->where('organizational_unit_id', $unit->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing->load(['unit', 'grantedBy']);
            }

            $scope = AmmsCapabilityScope::create([
                'user_id' => $user->id,
                'capability' => $capability,
                'organizational_unit_id' => $unit->id,
                'granted_by_user_id' => $grantedBy?->id,
                'granted_at' => now(),
            ]);

            $user->givePermissionTo($permissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $scope->load(['unit', 'grantedBy']);
        });
    }

    public function revoke(AmmsCapabilityScope $scope, ?User $revokedBy = null): AmmsCapabilityScope
    {
        return DB::transaction(function () use ($scope, $revokedBy): AmmsCapabilityScope {
            $current = AmmsCapabilityScope::query()->lockForUpdate()->findOrFail($scope->id);
            if (!$current->isActive()) {
                return $current->load(['unit', 'grantedBy', 'revokedBy']);
            }

            $current->update([
                'revoked_by_user_id' => $revokedBy?->id,
                'revoked_at' => now(),
            ]);

            $hasOtherActiveScope = AmmsCapabilityScope::query()
                ->active()
                ->where('user_id', $current->user_id)
                ->where('capability', $current->capability)
                ->exists();

            if (!$hasOtherActiveScope) {
                $user = $current->user;
                foreach (SpecialCapability::permissions($current->capability) as $permission) {
                    $user->revokePermissionTo($permission);
                }
                app(PermissionRegistrar::class)->forgetCachedPermissions();
            }

            return $current->fresh(['unit', 'grantedBy', 'revokedBy']);
        });
    }

    private function assertGrantableSubject(User $user, OrganizationalUnit $unit): void
    {
        $user->loadMissing('employee.unit');

        if (!$user->isActive() || !$user->employee || !$user->employee->unit) {
            throw ValidationException::withMessages([
                'user' => ['صلاحیت ویژه فقط به کاربر فعال دارای هویت تشکیلاتی قابل تفویض است.'],
            ]);
        }

        if ($user->hasRole('SYSTEM_ADMIN', 'sanctum')) {
            throw ValidationException::withMessages([
                'user' => ['نقش SYSTEM_ADMIN صرفاً فنی است و نمی‌تواند صلاحیت عملیاتی ویژه دریافت کند.'],
            ]);
        }

        if ((int) $user->employee->unit->organization_id !== (int) $unit->organization_id) {
            throw ValidationException::withMessages([
                'organizational_unit_id' => ['محدوده صلاحیت باید متعلق به همان اداره کاربر باشد.'],
            ]);
        }
    }
}
