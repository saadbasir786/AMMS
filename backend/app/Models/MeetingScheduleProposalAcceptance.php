<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingScheduleProposalAcceptance extends Model
{
    protected $fillable = [
        'meeting_schedule_proposal_id',
        'organizational_unit_id',
        'accepted_by',
        'accepted_at',
    ];

    protected $casts = ['accepted_at' => 'datetime'];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(MeetingScheduleProposal::class, 'meeting_schedule_proposal_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function accepter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
