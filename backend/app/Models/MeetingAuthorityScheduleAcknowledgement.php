<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAuthorityScheduleAcknowledgement extends Model
{
    protected $fillable = [
        'meeting_request_id',
        'meeting_schedule_proposal_id',
        'requesting_unit_id',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected $casts = ['acknowledged_at' => 'datetime'];

    public function request(): BelongsTo { return $this->belongsTo(MeetingRequest::class, 'meeting_request_id'); }
    public function proposal(): BelongsTo { return $this->belongsTo(MeetingScheduleProposal::class, 'meeting_schedule_proposal_id'); }
    public function requestingUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'requesting_unit_id'); }
    public function acknowledgedBy(): BelongsTo { return $this->belongsTo(User::class, 'acknowledged_by'); }
}
