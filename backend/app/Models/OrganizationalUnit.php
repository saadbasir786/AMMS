<?php

namespace App\Models;

use App\Support\OrganizationalUnitType;
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
    /** @deprecated Use OrganizationalUnitType::formalGovernance(). */
    public const FORMAL_GOVERNANCE_UNIT_TYPES = OrganizationalUnitType::FORMAL_GOVERNANCE;
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
        return $this->unit_type !== null && OrganizationalUnitType::isFormalGovernance($this->unit_type);
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
        return $query->whereIn('unit_type', OrganizationalUnitType::formalGovernance());
    }
}
