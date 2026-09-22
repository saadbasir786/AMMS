<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingParticipantChangeCase extends Model
{
    protected $fillable = [
        'meeting_id',
        'owner_type',
        'owner_unit_id',
        'baseline_participant_version_id',
        'baseline_schedule_version_id',
        'initiated_by_user_id',
        'reason',
        'status',
        'submitted_at',
        'submitted_by_user_id',
        'review_cycle',
        'approved_by_user_id',
        'approved_at',
        'approved_participant_version_id',
        'approved_schedule_version_id',
        'approved_draft_hash',
        'confirmed_at',
    ];

    protected $casts = [
        'review_cycle' => 'integer',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function ownerUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'owner_unit_id');
    }

    public function baselineParticipantVersion(): BelongsTo
    {
        return $this->belongsTo(MeetingParticipantVersion::class, 'baseline_participant_version_id');
    }

    public function baselineScheduleVersion(): BelongsTo
    {
        return $this->belongsTo(MeetingScheduleVersion::class, 'baseline_schedule_version_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MeetingParticipantChangeItem::class, 'change_case_id');
    }
}
