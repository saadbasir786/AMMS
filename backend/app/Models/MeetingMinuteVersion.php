<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingMinuteVersion extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'uuid', 'meeting_minutes_id', 'version_number', 'execution_session_id',
        'schedule_version_id', 'participant_version_id', 'summary', 'general_notes',
        'prepared_by_user_id', 'submitted_at',
    ];
    protected $casts = ['submitted_at' => 'datetime'];

    public function minutes(): BelongsTo { return $this->belongsTo(MeetingMinutes::class, 'meeting_minutes_id'); }
    public function executionSession(): BelongsTo { return $this->belongsTo(MeetingExecutionSession::class, 'execution_session_id'); }
    public function scheduleVersion(): BelongsTo { return $this->belongsTo(MeetingScheduleVersion::class, 'schedule_version_id'); }
    public function participantVersion(): BelongsTo { return $this->belongsTo(MeetingParticipantVersion::class, 'participant_version_id'); }
    public function preparedBy(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by_user_id'); }
    public function items(): HasMany { return $this->hasMany(MeetingMinuteVersionItem::class); }
    public function decisions(): HasMany { return $this->hasMany(MeetingMinuteVersionDecision::class); }
    public function attendanceSnapshots(): HasMany { return $this->hasMany(MeetingMinuteAttendanceSnapshot::class); }
    public function reviews(): HasMany { return $this->hasMany(MeetingMinuteReview::class); }
    public function document(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(MeetingMinuteDocument::class); }
}
