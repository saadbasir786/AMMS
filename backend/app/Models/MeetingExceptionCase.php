<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeetingExceptionCase extends \Illuminate\Database\Eloquent\Model
{
    public const POSTPONEMENT = 'POSTPONEMENT';
    public const CANCELLATION = 'CANCELLATION';
    public const REOPENING = 'REOPENING';
    public const RESUMPTION = 'RESUMPTION';

    public const DRAFT = 'DRAFT';
    public const UNDER_REVIEW = 'UNDER_REVIEW';
    public const RETURNED_FOR_CORRECTION = 'RETURNED_FOR_CORRECTION';
    public const READY_TO_APPLY = 'READY_TO_APPLY';
    public const REJECTED = 'REJECTED';
    public const WITHDRAWN = 'WITHDRAWN';
    public const APPLIED = 'APPLIED';

    public const ACTIVE_STATUSES = [self::DRAFT, self::UNDER_REVIEW, self::RETURNED_FOR_CORRECTION, self::READY_TO_APPLY];

    protected $table = 'meeting_exception_cases';
    protected $fillable = [
        'uuid', 'meeting_id', 'case_type', 'status', 'governance_mode',
        'baseline_meeting_status', 'baseline_schedule_version_id', 'baseline_participant_version_id',
        'current_version_id', 'created_by_user_id', 'created_at', 'applied_by_user_id', 'applied_at',
        'withdrawn_by_user_id', 'withdrawn_at', 'active_marker', 'updated_at',
    ];
    protected $casts = [
        'created_at' => 'datetime', 'applied_at' => 'datetime', 'withdrawn_at' => 'datetime', 'updated_at' => 'datetime',
    ];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function currentVersion(): BelongsTo { return $this->belongsTo(MeetingExceptionCaseVersion::class, 'current_version_id'); }
    public function versions(): HasMany { return $this->hasMany(MeetingExceptionCaseVersion::class, 'exception_case_id'); }
    public function reviews(): HasMany { return $this->hasMany(MeetingExceptionReview::class, 'exception_case_version_id', 'current_version_id'); }
    public function attendanceInvalidations(): HasMany { return $this->hasMany(MeetingAttendanceInvalidation::class, 'exception_case_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function appliedBy(): BelongsTo { return $this->belongsTo(User::class, 'applied_by_user_id'); }
    public function withdrawnBy(): BelongsTo { return $this->belongsTo(User::class, 'withdrawn_by_user_id'); }

    public function isActive(): bool { return in_array($this->status, self::ACTIVE_STATUSES, true); }
    public function isTerminal(): bool { return in_array($this->status, [self::REJECTED, self::WITHDRAWN, self::APPLIED], true); }
}
