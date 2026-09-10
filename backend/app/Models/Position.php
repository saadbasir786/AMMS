<?php

namespace App\Models;

use App\Support\PositionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    /**
     * Seniority levels represent organizational seniority only.
     * They MUST NOT automatically grant system permissions.
     */
    /** @deprecated Use PositionType::seniority(). Kept for consumer compatibility. */
    public const SENIORITY_LEVELS = PositionType::SENIORITY_LEVELS;

    /** @deprecated Use PositionType::all(). Kept for consumer compatibility. */
    public const POSITION_TYPES = PositionType::ALL;

    protected $fillable = ['organizational_unit_id', 'title', 'code', 'position_type', 'status'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function getSeniorityLevelAttribute(): int
    {
        return PositionType::seniority()[$this->position_type] ?? 50;
    }
}
