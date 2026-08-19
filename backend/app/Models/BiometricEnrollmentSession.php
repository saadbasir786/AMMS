<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricEnrollmentSession extends Model
{
    public const PENDING_CAPTURE = 'PENDING_CAPTURE';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const COMPLETED = 'COMPLETED';
    public const CANCELLED = 'CANCELLED';
    public const EXPIRED = 'EXPIRED';

    protected $fillable = [
        'uuid', 'organization_id', 'employee_id', 'terminal_id', 'started_by_user_id',
        'status', 'required_finger_count', 'started_at', 'expires_at',
        'completed_at', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'required_finger_count' => 'integer',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function terminal(): BelongsTo { return $this->belongsTo(Terminal::class); }
    public function startedBy(): BelongsTo { return $this->belongsTo(User::class, 'started_by_user_id'); }
    public function templates(): HasMany { return $this->hasMany(EmployeeBiometricTemplate::class, 'enrollment_session_id'); }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::PENDING_CAPTURE, self::IN_PROGRESS]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::PENDING_CAPTURE, self::IN_PROGRESS], true) && !$this->isExpired();
    }
}
