<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipantVersionMember extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'participant_version_id',
        'employee_id',
        'organizational_unit_id',
        'participant_role',
        'owner_type',
        'owner_unit_id',
        'is_mandatory',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(MeetingParticipantVersion::class, 'participant_version_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function organizationalUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class);
    }

    public function ownerUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'owner_unit_id');
    }
}
