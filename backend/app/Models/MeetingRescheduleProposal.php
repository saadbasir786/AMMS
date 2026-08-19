<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingRescheduleProposal extends Model
{
    protected $fillable = [
        'reschedule_case_id', 'meeting_id', 'proposed_by_user_id', 'proposed_by_unit_id',
        'proposal_type', 'meeting_date', 'start_time', 'end_time', 'location', 'comment',
        'status', 'supersedes_proposal_id',
    ];

    protected $casts = ['meeting_date' => 'date'];

    public function rescheduleCase(): BelongsTo { return $this->belongsTo(MeetingRescheduleCase::class, 'reschedule_case_id'); }
    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function proposedBy(): BelongsTo { return $this->belongsTo(User::class, 'proposed_by_user_id'); }
    public function proposedByUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'proposed_by_unit_id'); }
    public function supersedes(): BelongsTo { return $this->belongsTo(self::class, 'supersedes_proposal_id'); }
    public function acknowledgements(): HasMany { return $this->hasMany(MeetingRescheduleAcknowledgement::class, 'proposal_id'); }
    public function alternativeRequests(): HasMany { return $this->hasMany(MeetingRescheduleAlternativeRequest::class, 'proposal_id'); }
}
