<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationalUnit extends Model
{
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
}
