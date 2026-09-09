<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationalUnit extends Model
{
    /**
     * Formal governance unit types capable of participating in formal inter-unit,
     * multi-unit, and high-authority meetings and requests.
     * Note: unit_type is classification metadata; actual authorization depends on
     * scoped roles, capabilities, delegations, and workflow rules.
     */
    public const FORMAL_GOVERNANCE_UNIT_TYPES = [
        'GENERAL_PRESIDENCY',
        'DEPUTY_PRESIDENCY',
        'GENERAL_DIRECTORATE',
        'DIRECTORATE',
        'ATTACHED_AGENCY',
        'DEPUTY_DIRECTORATE',
    ];

    protected $fillable = ['organization_id', 'parent_id', 'name', 'code', 'unit_type', 'status'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function employees(): HasMany { return $this->hasMany(Employee::class); }
    public function positions(): HasMany { return $this->hasMany(Position::class); }
    public function requests(): HasMany { return $this->hasMany(MeetingRequest::class, 'requesting_unit_id'); }

    public function isWithinScope(int $unitId): bool
    {
        if ($this->id === $unitId) return true;
        foreach ($this->children as $child) if ($child->isWithinScope($unitId)) return true;
        return false;
    }

    /**
     * Determines whether this unit qualifies as a formal governance unit.
     */
    public function isFormalGovernanceUnit(): bool
    {
        return in_array($this->unit_type, self::FORMAL_GOVERNANCE_UNIT_TYPES, true);
    }

    /**
     * Climbs up the hierarchy starting from this unit to find the nearest
     * governing formal governance unit (e.g., Directorate, Agency, General Directorate, or Presidency).
     */
    public function formalGovernanceUnit(): ?self
    {
        $current = $this;
        $visited = [];
        while ($current && !in_array($current->id, $visited, true)) {
            $visited[] = $current->id;
            if ($current->isFormalGovernanceUnit()) {
                return $current;
            }
            $current = $current->parent;
        }
        return null;
    }

    public function scopeFormalGovernance(Builder $query): Builder
    {
        return $query->whereIn('unit_type', self::FORMAL_GOVERNANCE_UNIT_TYPES);
    }
}
