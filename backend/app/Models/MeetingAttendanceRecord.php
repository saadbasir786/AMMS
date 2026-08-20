<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeetingAttendanceRecord extends Model
{
    protected $fillable = [
        'uuid','meeting_id','employee_id','participant_version_id','terminal_id','terminal_session_id',
        'attendance_method','finger_position','matched_template_id','matcher_vendor','matcher_algorithm_version',
        'match_score','threshold_reference','candidate_set_checksum','client_event_uuid','recorded_at_server',
        'device_captured_at','recorded_by_user_id','manual_reason',
    ];

    protected $casts = ['match_score' => 'decimal:5', 'recorded_at_server' => 'datetime', 'device_captured_at' => 'datetime'];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function participantVersion(): BelongsTo { return $this->belongsTo(MeetingParticipantVersion::class, 'participant_version_id'); }
    public function terminal(): BelongsTo { return $this->belongsTo(Terminal::class); }
    public function terminalSession(): BelongsTo { return $this->belongsTo(TerminalMeetingSession::class, 'terminal_session_id'); }
    public function matchedTemplate(): BelongsTo { return $this->belongsTo(EmployeeBiometricTemplate::class, 'matched_template_id'); }
    public function invalidation(): HasOne { return $this->hasOne(MeetingAttendanceInvalidation::class, 'meeting_attendance_record_id'); }
    public function recordedBy(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by_user_id'); }

    protected static function booted(): void
    {
        static::updating(fn (): never => abort(409, 'ATTENDANCE_IMMUTABLE'));
        static::deleting(fn (): never => abort(409, 'ATTENDANCE_IMMUTABLE'));
    }
}
