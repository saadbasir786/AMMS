<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRequestAuthorityContext extends Model
{
    protected $fillable = [
        'meeting_request_id',
        'organization_authority_id',
        'authority_type',
        'authority_office_unit_id',
        'authority_position_id',
        'authority_employee_id',
        'office_review_status',
        'office_reviewed_by',
        'office_reviewed_at',
        'office_review_comment',
        'forwarded_by',
        'forwarded_at',
        'authority_decision_status',
        'authority_decided_by',
        'authority_decided_at',
        'authority_decision_comment',
    ];

    protected $casts = [
        'office_reviewed_at' => 'datetime',
        'forwarded_at' => 'datetime',
        'authority_decided_at' => 'datetime',
    ];

    public function request(): BelongsTo { return $this->belongsTo(MeetingRequest::class, 'meeting_request_id'); }
    public function organizationAuthority(): BelongsTo { return $this->belongsTo(OrganizationAuthority::class); }
    public function authorityOfficeUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'authority_office_unit_id'); }
    public function authorityPosition(): BelongsTo { return $this->belongsTo(Position::class, 'authority_position_id'); }
    public function authorityEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'authority_employee_id'); }
    public function officeReviewer(): BelongsTo { return $this->belongsTo(User::class, 'office_reviewed_by'); }
    public function forwarder(): BelongsTo { return $this->belongsTo(User::class, 'forwarded_by'); }
    public function authorityDecider(): BelongsTo { return $this->belongsTo(User::class, 'authority_decided_by'); }
}
