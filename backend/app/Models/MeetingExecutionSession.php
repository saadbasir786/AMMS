<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingExecutionSession extends \Illuminate\Database\Eloquent\Model
{
    public const IN_PROGRESS = 'IN_PROGRESS';
    public const ENDED = 'ENDED';

    protected $fillable = [
        'uuid', 'meeting_id', 'schedule_version_id', 'participant_version_id',
        'status', 'started_at', 'started_by_user_id', 'ended_at', 'ended_by_user_id',
        'start_notes', 'end_notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function scheduleVersion(): BelongsTo { return $this->belongsTo(MeetingScheduleVersion::class, 'schedule_version_id'); }
    public function participantVersion(): BelongsTo { return $this->belongsTo(MeetingParticipantVersion::class, 'participant_version_id'); }
    public function startedBy(): BelongsTo { return $this->belongsTo(User::class, 'started_by_user_id'); }
    public function endedBy(): BelongsTo { return $this->belongsTo(User::class, 'ended_by_user_id'); }

    public function isActive(): bool
    {
        return $this->status === self::IN_PROGRESS;
    }
}
