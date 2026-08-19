<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRescheduleAlternativeRequest extends Model
{
    protected $fillable = [
        'reschedule_case_id', 'proposal_id', 'organizational_unit_id', 'requested_by_user_id',
        'reason', 'suggested_date', 'suggested_start_time', 'suggested_end_time', 'status',
        'resolved_at', 'resolved_by_user_id',
    ];

    protected $casts = ['suggested_date' => 'date', 'resolved_at' => 'datetime'];

    public function rescheduleCase(): BelongsTo { return $this->belongsTo(MeetingRescheduleCase::class, 'reschedule_case_id'); }
    public function proposal(): BelongsTo { return $this->belongsTo(MeetingRescheduleProposal::class, 'proposal_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id'); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function resolvedBy(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by_user_id'); }
}
