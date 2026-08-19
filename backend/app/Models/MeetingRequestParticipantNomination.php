<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRequestParticipantNomination extends Model
{
    protected $fillable = ['meeting_request_id', 'employee_id', 'organizational_unit_id', 'nominated_by_unit_id', 'nominated_by_user_id', 'participant_stage', 'nomination_status'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MeetingRequest::class, 'meeting_request_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function nominatedByUnit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'nominated_by_unit_id');
    }

    public function nominatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominated_by_user_id');
    }
}
