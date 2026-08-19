<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRescheduleAcknowledgement extends Model
{
    public $timestamps = false;

    protected $fillable = ['proposal_id', 'organizational_unit_id', 'acknowledged_by_user_id', 'acknowledged_at'];
    protected $casts = ['acknowledged_at' => 'datetime'];

    public function proposal(): BelongsTo { return $this->belongsTo(MeetingRescheduleProposal::class, 'proposal_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id'); }
    public function acknowledgedBy(): BelongsTo { return $this->belongsTo(User::class, 'acknowledged_by_user_id'); }
}
