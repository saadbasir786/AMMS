<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    /**
     * Seniority levels represent organizational seniority only.
     * They MUST NOT automatically grant system permissions.
     */
    public const SENIORITY_LEVELS = [
        'DIRECTOR' => 100,
        'DEPUTY_DIRECTOR' => 90,
        'AMIR' => 80,
        'GENERAL_MANAGER' => 70,
        'EXPERT' => 70,
        'MANAGER' => 60,
        'EMPLOYEE' => 50,
    ];

    public const POSITION_TYPES = [
        'DIRECTOR',
        'DEPUTY_DIRECTOR',
        'AMIR',
        'GENERAL_MANAGER',
        'EXPERT',
        'MANAGER',
        'EMPLOYEE',
        'EXECUTIVE_MANAGEMENT',
        'HIGH_AUTHORITY',
        'AUTHORITY_OFFICE',
        'EXECUTIVE_SECRETARY',
        'OTHER',
    ];

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
        return self::SENIORITY_LEVELS[$this->position_type] ?? 50;
    }
}