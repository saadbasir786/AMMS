<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRequestUnit extends Model
{
    protected $fillable = ['meeting_request_id', 'organizational_unit_id', 'role'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MeetingRequest::class, 'meeting_request_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id');
    }
}
