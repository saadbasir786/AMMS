<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingDecisionFollowUp extends \Illuminate\Database\Eloquent\Model
{
    public const UNCLASSIFIED = 'UNCLASSIFIED';
    public const ACTION_REQUIRED = 'ACTION_REQUIRED';
    public const NO_ACTION_REQUIRED = 'NO_ACTION_REQUIRED';
    public const OPEN = 'OPEN';
    public const ACTIVE = 'ACTIVE';
    public const CLOSED = 'CLOSED';

    protected $table = 'meeting_decision_followups';
    protected $fillable = [
        'uuid', 'meeting_id', 'meeting_minutes_id', 'meeting_minute_version_id',
        'meeting_decision_id', 'decision_number', 'classification', 'status', 'classified_by_user_id',
        'classified_at', 'no_action_reason',
    ];
    protected $casts = ['classified_at' => 'datetime'];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function minutes(): BelongsTo { return $this->belongsTo(MeetingMinutes::class, 'meeting_minutes_id'); }
    public function version(): BelongsTo { return $this->belongsTo(MeetingMinuteVersion::class, 'meeting_minute_version_id'); }
    public function decision(): BelongsTo { return $this->belongsTo(MeetingMinuteVersionDecision::class, 'meeting_decision_id'); }
    public function classifiedBy(): BelongsTo { return $this->belongsTo(User::class, 'classified_by_user_id'); }
    public function actionItems(): HasMany { return $this->hasMany(MeetingActionItem::class, 'decision_followup_id'); }

    public function isActionRequired(): bool { return $this->classification === self::ACTION_REQUIRED; }

    public function hasOperationalItems(): bool { return $this->actionItems()->exists(); }
}
