<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingRescheduleCase extends Model
{
    protected $fillable = [
        'meeting_id', 'baseline_schedule_version_id', 'initiated_by_user_id', 'initiated_by_unit_id',
        'reason', 'governance_mode', 'status', 'confirmed_at',
    ];

    protected $casts = ['confirmed_at' => 'datetime'];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function baselineScheduleVersion(): BelongsTo { return $this->belongsTo(MeetingScheduleVersion::class, 'baseline_schedule_version_id'); }
    public function initiatedBy(): BelongsTo { return $this->belongsTo(User::class, 'initiated_by_user_id'); }
    public function initiatedByUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'initiated_by_unit_id'); }
    public function proposals(): HasMany { return $this->hasMany(MeetingRescheduleProposal::class, 'reschedule_case_id'); }
    public function alternativeRequests(): HasMany { return $this->hasMany(MeetingRescheduleAlternativeRequest::class, 'reschedule_case_id'); }
}
