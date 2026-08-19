<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAuthorityContext extends Model
{
    protected $fillable = [
        'meeting_id',
        'organization_authority_id',
        'authority_type',
        'authority_position_id',
        'authority_employee_id',
        'authority_office_unit_id',
    ];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function organizationAuthority(): BelongsTo { return $this->belongsTo(OrganizationAuthority::class); }
    public function authorityPosition(): BelongsTo { return $this->belongsTo(Position::class, 'authority_position_id'); }
    public function authorityEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'authority_employee_id'); }
    public function authorityOfficeUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'authority_office_unit_id'); }
}
