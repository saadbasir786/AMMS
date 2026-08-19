<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingMinuteReview extends \Illuminate\Database\Eloquent\Model
{
    public const UNIT = 'UNIT';
    public const HIGH_AUTHORITY = 'HIGH_AUTHORITY';
    public const PENDING = 'PENDING';
    public const ACKNOWLEDGED = 'ACKNOWLEDGED';
    public const CORRECTION_REQUESTED = 'CORRECTION_REQUESTED';
    public const APPROVED = 'APPROVED';

    protected $fillable = [
        'meeting_minute_version_id', 'reviewer_type', 'organizational_unit_id',
        'authority_employee_id', 'status', 'reviewed_by_user_id', 'reason', 'reviewed_at',
    ];
    protected $casts = ['reviewed_at' => 'datetime'];

    public function version(): BelongsTo { return $this->belongsTo(MeetingMinuteVersion::class, 'meeting_minute_version_id'); }
    public function organizationalUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id'); }
    public function authorityEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'authority_employee_id'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by_user_id'); }
}
