<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingRequestDirectiveVersion extends Model
{
    protected $fillable = [
        'meeting_request_id', 'version', 'version_status', 'subject', 'purpose', 'description',
        'directive', 'meeting_justification', 'requested_outcome', 'priority', 'preferred_date',
        'preferred_start_time', 'estimated_duration_minutes', 'preferred_location', 'proposed_agenda',
        'participating_unit_ids', 'prepared_by', 'issued_by', 'authority_employee_id',
        'authority_position_id', 'issued_at', 'is_current',
    ];

    protected $casts = [
        'participating_unit_ids' => 'array',
        'preferred_date' => 'date',
        'issued_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    public function request(): BelongsTo { return $this->belongsTo(MeetingRequest::class, 'meeting_request_id'); }
    public function preparedBy(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function authorityEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'authority_employee_id'); }
    public function authorityPosition(): BelongsTo { return $this->belongsTo(Position::class, 'authority_position_id'); }
    public function unitResponses(): HasMany { return $this->hasMany(MeetingRequestUnitResponse::class); }
    public function clarifications(): HasMany { return $this->hasMany(MeetingRequestDirectiveClarification::class); }
}
