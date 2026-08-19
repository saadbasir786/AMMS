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

    public function isActive(): bool
    {
        return ($this->status ?: 'ACTIVE') === 'ACTIVE' && (!$this->employee || $this->employee->status === 'ACTIVE');
    }
}
