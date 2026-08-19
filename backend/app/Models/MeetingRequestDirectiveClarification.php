<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRequestDirectiveClarification extends Model
{
    protected $fillable = [
        'meeting_request_id', 'meeting_request_directive_version_id', 'organizational_unit_id',
        'requested_by', 'requested_at', 'reason', 'responded_by', 'responded_at', 'response',
    ];

    protected $casts = ['requested_at' => 'datetime', 'responded_at' => 'datetime'];

    public function request(): BelongsTo { return $this->belongsTo(MeetingRequest::class, 'meeting_request_id'); }
    public function directiveVersion(): BelongsTo { return $this->belongsTo(MeetingRequestDirectiveVersion::class, 'meeting_request_directive_version_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function responder(): BelongsTo { return $this->belongsTo(User::class, 'responded_by'); }
}
