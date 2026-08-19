<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRequestUnitReview extends Model
{
    protected $fillable = [
        'meeting_request_id',
        'organizational_unit_id',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'comment',
    ];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MeetingRequest::class, 'meeting_request_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
