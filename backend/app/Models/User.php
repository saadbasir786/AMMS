<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

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

    public function hasScopedRole(string $role, ?int $unitId = null): bool
    {
        if (!$this->hasRole($role, 'sanctum')) return false;
        return $this->roleScopes()->whereHas('role', fn ($q) => $q->where('name', $role)->where('guard_name', 'sanctum'))->where(function ($query) use ($unitId) {
            $query->whereNull('organizational_unit_id');
            if ($unitId !== null) $query->orWhere('organizational_unit_id', $unitId);
        })->exists();
    }

    public function scopedRoleUnitIds(string $role): array
    {
        if (!$this->hasRole($role, 'sanctum')) return [];
        return $this->roleScopes()->whereHas('role', fn ($q) => $q->where('name', $role)->where('guard_name', 'sanctum'))->pluck('organizational_unit_id')->filter()->values()->all();
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
