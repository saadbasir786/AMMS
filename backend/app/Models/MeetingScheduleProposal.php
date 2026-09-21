<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingScheduleProposal extends Model
{
    protected $fillable = ['meeting_request_id', 'proposed_by_unit_id', 'proposed_by_user_id', 'meeting_date', 'start_time', 'end_time', 'location', 'venue_type', 'venue_id', 'venue_snapshot', 'comment', 'status', 'proposal_type'];
    protected $casts = ['meeting_date' => 'date', 'venue_snapshot' => 'array'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MeetingRequest::class, 'meeting_request_id');
    }

    public function proposedByUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'proposed_by_unit_id');
    }

    public function proposedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by_user_id');
    }

    public function venue(): BelongsTo { return $this->belongsTo(MeetingVenue::class, 'venue_id'); }

    public function acceptances(): HasMany
    {
        return $this->hasMany(MeetingScheduleProposalAcceptance::class, 'meeting_schedule_proposal_id');
    }

    public function authorityAcknowledgements(): HasMany
    {
        return $this->hasMany(MeetingAuthorityScheduleAcknowledgement::class, 'meeting_schedule_proposal_id');
    }

    public function authorityUnitAcknowledgements(): HasMany
    {
        return $this->hasMany(MeetingAuthorityScheduleUnitAcknowledgement::class, 'meeting_schedule_proposal_id');
    }
}
