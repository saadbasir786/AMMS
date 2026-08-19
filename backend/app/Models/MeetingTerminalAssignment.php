<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingTerminalAssignment extends Model
{
    protected $fillable = [
        'uuid', 'meeting_id', 'terminal_id', 'baseline_schedule_version_id',
        'baseline_participant_version_id', 'valid_from', 'valid_until', 'status',
        'assigned_by_user_id', 'assigned_at', 'revoked_by_user_id', 'revoked_at',
        'revoke_reason', 'refreshed_at',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'assigned_at' => 'datetime',
        'revoked_at' => 'datetime',
        'refreshed_at' => 'datetime',
    ];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function terminal(): BelongsTo { return $this->belongsTo(Terminal::class); }
    public function baselineScheduleVersion(): BelongsTo { return $this->belongsTo(MeetingScheduleVersion::class, 'baseline_schedule_version_id'); }
    public function baselineParticipantVersion(): BelongsTo { return $this->belongsTo(MeetingParticipantVersion::class, 'baseline_participant_version_id'); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by_user_id'); }
    public function revokedBy(): BelongsTo { return $this->belongsTo(User::class, 'revoked_by_user_id'); }
    public function sessions(): HasMany { return $this->hasMany(TerminalMeetingSession::class, 'assignment_id'); }

    public function staleness(?Meeting $meeting = null): array
    {
        $meeting ??= $this->relationLoaded('meeting') ? $this->meeting : $this->meeting()->firstOrFail();
        return [
            'schedule_changed' => (int) $this->baseline_schedule_version_id !== (int) $meeting->current_schedule_version_id,
            'participants_changed' => (int) $this->baseline_participant_version_id !== (int) $meeting->current_participant_version_id,
        ];
    }

    public function isStale(?Meeting $meeting = null): bool
    {
        return in_array(true, $this->staleness($meeting), true);
    }

    public function isWindowOpen(?\DateTimeInterface $at = null): bool
    {
        $at ??= now();
        return $this->valid_from <= $at && $this->valid_until >= $at;
    }
}
