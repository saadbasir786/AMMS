<?php

namespace App\Models;

use App\Support\AmmsRole;
use App\Support\AmmsPermissionRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles {
        hasPermissionTo as protected spatieHasPermissionTo;
    }

    protected $fillable = ['name', 'email', 'password', 'employee_id', 'status'];
    protected $hidden = ['password', 'remember_token'];
    protected $guard_name = 'sanctum';
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'password' => 'hashed']; }

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function legacyRoles(): HasMany { return $this->hasMany(UserRole::class); }
    public function roleScopes(): HasMany { return $this->hasMany(AmmsRoleScope::class); }
    public function capabilityScopes(): HasMany { return $this->hasMany(AmmsCapabilityScope::class); }
    public function accessAudits(): HasMany { return $this->hasMany(UserAccessAudit::class); }
    public function notifications(): HasMany { return $this->hasMany(AmmsNotification::class); }

    /**
     * Keep inactive custom roles from remaining effective through Spatie's
     * permission cache. Canonical role identity is intentionally not inferred
     * from custom role names.
     */
    public function hasPermissionTo($permission, ?string $guardName = null): bool
    {
        $this->spatieHasPermissionTo($permission, $guardName);

        $guard = $guardName ?: 'sanctum';
        $resolved = $permission instanceof Permission
            ? $permission
            : Permission::findByName((string) $permission, $guard);

        if (! $this->isActive()) return false;
        if (! in_array($resolved->name, AmmsPermissionRegistry::allPermissions(), true)) return false;
        if ($this->hasDirectPermission($resolved)) return true;

        return $this->roles()
            ->where('roles.guard_name', $guard)
            ->whereHas('permissions', fn ($query) => $query->where('permissions.id', $resolved->id))
            ->where(function ($query): void {
                $query->whereIn('roles.name', AmmsRole::all())
                    ->orWhereExists(function ($definition): void {
                        $definition->selectRaw('1')
                            ->from('amms_role_definitions')
                            ->whereColumn('amms_role_definitions.role_id', 'roles.id')
                            ->where('amms_role_definitions.role_kind', 'CUSTOM')
                            ->where('amms_role_definitions.status', 'ACTIVE');
                    });
            })
            ->exists();
    }

    public function hasScopedRole(string $role, ?int $unitId = null): bool
    {
        if (! $this->isActive() || ! AmmsRole::isValid($role) || ! $this->hasRole($role, 'sanctum')) return false;
        return $this->roleScopes()->whereHas('role', fn ($q) => $q->where('name', $role)->where('guard_name', 'sanctum'))->where(function ($query) use ($unitId) {
            $query->whereNull('organizational_unit_id');
            if ($unitId !== null) $query->orWhere('organizational_unit_id', $unitId);
        })->exists();
    }

    public function scopedRoleUnitIds(string $role): array
    {
        if (! $this->isActive() || ! AmmsRole::isValid($role) || ! $this->hasRole($role, 'sanctum')) return [];
        return $this->roleScopes()->whereHas('role', fn ($q) => $q->where('name', $role)->where('guard_name', 'sanctum'))->pluck('organizational_unit_id')->filter()->values()->all();
    }

    public function scopedPermissionUnitIds(string $permission): array
    {
        if (! $this->isActive()) return [];

        $roleIds = $this->roleIdsForPermission($permission);

        return $this->roleScopes()
            ->whereIn('role_id', $roleIds)
            ->whereHas('unit', fn ($unit) => $unit->where('status', 'ACTIVE'))
            ->pluck('organizational_unit_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function hasScopedPermission(string $permission, ?int $unitId = null): bool
    {
        if (! $this->hasPermissionTo($permission, 'sanctum')) return false;

        $query = $this->roleScopes()->whereIn('role_id', $this->roleIdsForPermission($permission));
        $query->where(function ($scope): void {
            $scope->whereNull('organizational_unit_id')->orWhereHas('unit', fn ($unit) => $unit->where('status', 'ACTIVE'));
        });
        if ($unitId === null) return $query->exists();

        return $query->where('organizational_unit_id', $unitId)->exists();
    }

    private function roleIdsForPermission(string $permission): \Illuminate\Support\Collection
    {
        return \DB::table('roles')
            ->join('role_has_permissions', 'role_has_permissions.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('roles.guard_name', 'sanctum')
            ->where('permissions.guard_name', 'sanctum')
            ->where('permissions.name', $permission)
            ->where(function ($query): void {
                $query->whereIn('roles.name', AmmsRole::all())
                    ->orWhereExists(function ($definition): void {
                        $definition->selectRaw('1')->from('amms_role_definitions')
                            ->whereColumn('amms_role_definitions.role_id', 'roles.id')
                            ->where('amms_role_definitions.role_kind', 'CUSTOM')
                            ->where('amms_role_definitions.status', 'ACTIVE');
                    });
            })
            ->pluck('roles.id');
    }

    public function hasScopedCapability(string $capability, int $unitId): bool
    {
        if (!$this->isActive()) return false;

        $scopeUnitIds = $this->capabilityScopes()
            ->active()
            ->where('capability', $capability)
            ->pluck('organizational_unit_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->flip();

        if ($scopeUnitIds->isEmpty()) return false;

        $currentUnitId = $unitId;
        $visited = [];
        while ($currentUnitId > 0 && !isset($visited[$currentUnitId])) {
            if ($scopeUnitIds->has($currentUnitId)) return true;
            $visited[$currentUnitId] = true;
            $currentUnitId = (int) (OrganizationalUnit::query()->whereKey($currentUnitId)->value('parent_id') ?? 0);
        }

        return false;
    }

    public function hasExactScopedCapability(string $capability, int $unitId): bool
    {
        return $this->isActive()
            && $this->capabilityScopes()
                ->active()
                ->where('capability', $capability)
                ->where('organizational_unit_id', $unitId)
                ->exists();
    }

    public function scopedCapabilityUnitIds(string $capability): array
    {
        if (!$this->isActive()) return [];
        return $this->capabilityScopes()
            ->active()
            ->where('capability', $capability)
            ->pluck('organizational_unit_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function isActive(): bool
    {
        return ($this->status ?: 'ACTIVE') === 'ACTIVE' && (!$this->employee || $this->employee->status === 'ACTIVE');
    }
}
