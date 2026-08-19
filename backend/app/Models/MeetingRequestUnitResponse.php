<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRequestUnitResponse extends Model
{
    protected $fillable = [
        'meeting_request_id', 'meeting_request_directive_version_id', 'organizational_unit_id',
        'response_status', 'coordination_started_by', 'coordination_started_at',
        'acknowledged_by', 'acknowledged_at',
    ];

    protected $casts = ['coordination_started_at' => 'datetime', 'acknowledged_at' => 'datetime'];

    public function request(): BelongsTo { return $this->belongsTo(MeetingRequest::class, 'meeting_request_id'); }
    public function directiveVersion(): BelongsTo { return $this->belongsTo(MeetingRequestDirectiveVersion::class, 'meeting_request_directive_version_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id'); }
    public function coordinationStarter(): BelongsTo { return $this->belongsTo(User::class, 'coordination_started_by'); }
    public function acknowledger(): BelongsTo { return $this->belongsTo(User::class, 'acknowledged_by'); }
}
