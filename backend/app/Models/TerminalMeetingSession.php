<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalMeetingSession extends Model
{
    protected $fillable = [
        'uuid', 'assignment_id', 'terminal_id', 'meeting_id', 'schedule_version_id',
        'participant_version_id', 'status', 'session_token_hash', 'activated_at',
        'expires_at', 'closed_at', 'close_reason',
    ];

    protected $hidden = ['session_token_hash'];

    protected $casts = [
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function assignment(): BelongsTo { return $this->belongsTo(MeetingTerminalAssignment::class, 'assignment_id'); }
    public function terminal(): BelongsTo { return $this->belongsTo(Terminal::class); }
    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function scheduleVersion(): BelongsTo { return $this->belongsTo(MeetingScheduleVersion::class, 'schedule_version_id'); }
    public function participantVersion(): BelongsTo { return $this->belongsTo(MeetingParticipantVersion::class, 'participant_version_id'); }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->greaterThanOrEqualTo($this->expires_at);
    }

    public function isEffectivelyActive(): bool
    {
        return $this->status === 'ACTIVE' && !$this->isExpired();
    }
}
