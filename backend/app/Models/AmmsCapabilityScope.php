<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmmsCapabilityScope extends Model
{
    protected $fillable = [
        'user_id',
        'capability',
        'organizational_unit_id',
        'granted_by_user_id',
        'granted_at',
        'expires_at',
        'grant_reason',
        'revoked_by_user_id',
        'revoked_at',
        'revocation_reason',
    ];

    protected $casts = [
        'granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function unit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id'); }
    public function grantedBy(): BelongsTo { return $this->belongsTo(User::class, 'granted_by_user_id'); }
    public function revokedBy(): BelongsTo { return $this->belongsTo(User::class, 'revoked_by_user_id'); }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('revoked_at')
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
