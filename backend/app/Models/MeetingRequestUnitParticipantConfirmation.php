<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRequestUnitParticipantConfirmation extends Model
{
    protected $fillable = [
        'meeting_request_id',
        'organizational_unit_id',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = ['confirmed_at' => 'datetime'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MeetingRequest::class, 'meeting_request_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
