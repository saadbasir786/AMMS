<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'terminal_id', 'meeting_id', 'assignment_id', 'session_id', 'actor_id', 'action', 'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function terminal(): BelongsTo { return $this->belongsTo(Terminal::class); }
    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function assignment(): BelongsTo { return $this->belongsTo(MeetingTerminalAssignment::class, 'assignment_id'); }
    public function session(): BelongsTo { return $this->belongsTo(TerminalMeetingSession::class, 'session_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }

    protected static function booted(): void
    {
        static::updating(fn (): bool => throw new \LogicException('Terminal audit events are immutable.'));
        static::deleting(fn (): bool => throw new \LogicException('Terminal audit events are immutable.'));
    }
}
