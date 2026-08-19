<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationAuthority extends Model
{
    protected $fillable = [
        'organization_id',
        'authority_type',
        'authority_position_id',
        'authority_office_unit_id',
        'status',
        'is_primary',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function authorityPosition(): BelongsTo { return $this->belongsTo(Position::class, 'authority_position_id'); }
    public function authorityOfficeUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'authority_office_unit_id'); }
    public function requestContexts(): HasMany { return $this->hasMany(MeetingRequestAuthorityContext::class); }
    public function meetingContexts(): HasMany { return $this->hasMany(MeetingAuthorityContext::class); }

    public function isActivePrimary(): bool
    {
        return $this->status === 'ACTIVE' && $this->is_primary === true;
    }
}
