<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingActionItem extends \Illuminate\Database\Eloquent\Model
{
    public const OPEN = 'OPEN';
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const BLOCKED = 'BLOCKED';
    public const PENDING_VERIFICATION = 'PENDING_VERIFICATION';
    public const COMPLETED = 'COMPLETED';
    public const CANCELLED = 'CANCELLED';

    protected $table = 'meeting_action_items';
    protected $fillable = [
        'uuid', 'decision_followup_id', 'sequence_number', 'title', 'description',
        'responsible_unit_id', 'assigned_employee_id', 'status', 'progress_percent',
        'due_date', 'started_at', 'submitted_for_verification_at', 'submitted_by_user_id',
        'completed_at', 'completed_by_user_id', 'cancelled_at', 'cancelled_by_user_id',
        'cancel_reason', 'created_by_user_id', 'lock_version',
    ];
    protected $casts = [
        'due_date' => 'date',
        'started_at' => 'datetime',
        'submitted_for_verification_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'progress_percent' => 'integer',
        'lock_version' => 'integer',
    ];
    protected $appends = ['action_number', 'is_overdue', 'days_overdue', 'is_due_soon'];

    public function followup(): BelongsTo { return $this->belongsTo(MeetingDecisionFollowUp::class, 'decision_followup_id'); }
    public function responsibleUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'responsible_unit_id'); }
    public function assignedEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'assigned_employee_id'); }
    public function submittedBy(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by_user_id'); }
    public function completedBy(): BelongsTo { return $this->belongsTo(User::class, 'completed_by_user_id'); }
    public function cancelledBy(): BelongsTo { return $this->belongsTo(User::class, 'cancelled_by_user_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function updates(): HasMany { return $this->hasMany(MeetingActionItemUpdate::class, 'action_item_id'); }
    public function extensionRequests(): HasMany { return $this->hasMany(MeetingActionItemExtensionRequest::class, 'action_item_id'); }
    public function assignmentHistory(): HasMany { return $this->hasMany(MeetingActionItemAssignmentHistory::class, 'action_item_id'); }
    public function evidence(): HasMany { return $this->hasMany(MeetingActionItemEvidence::class, 'action_item_id'); }

    public function getActionNumberAttribute(): string
    {
        $decisionNumber = $this->relationLoaded('followup') && $this->followup
            ? $this->followup->decision_number
            : ($this->followup?->decision_number ?? 'DECISION');
        return sprintf('%s-A%02d', $decisionNumber, (int) $this->sequence_number);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date instanceof CarbonInterface
            && $this->due_date->startOfDay()->isBefore(now()->startOfDay())
            && !in_array($this->status, [self::COMPLETED, self::CANCELLED], true);
    }

    public function getDaysOverdueAttribute(): int
    {
        return $this->is_overdue ? (int) $this->due_date->startOfDay()->diffInDays(now()->startOfDay()) : 0;
    }

    public function getIsDueSoonAttribute(): bool
    {
        if (!$this->due_date || in_array($this->status, [self::COMPLETED, self::CANCELLED], true)) return false;
        $threshold = max(0, (int) config('amms.action_items.due_soon_days', 3));
        return now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false) >= 0
            && now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false) <= $threshold;
    }

    public function isTerminal(): bool { return in_array($this->status, [self::COMPLETED, self::CANCELLED], true); }
}
