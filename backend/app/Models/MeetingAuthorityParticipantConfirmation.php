<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAuthorityParticipantConfirmation extends Model
{
    protected $fillable = [
        'meeting_request_id',
        'side',
        'confirmed_by',
        'confirmed_at',
    ];

    protected $casts = ['confirmed_at' => 'datetime'];

    public function request(): BelongsTo { return $this->belongsTo(MeetingRequest::class, 'meeting_request_id'); }
    public function confirmedBy(): BelongsTo { return $this->belongsTo(User::class, 'confirmed_by'); }
}
