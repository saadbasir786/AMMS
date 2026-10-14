<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBiometricTemplate extends Model
{
    public const ACTIVE = 'ACTIVE';
    public const SUPERSEDED = 'SUPERSEDED';
    public const REVOKED = 'REVOKED';

    protected $fillable = [
        'uuid', 'organization_id', 'employee_id', 'modality', 'finger_position',
        'template_vendor', 'template_format', 'algorithm_version', 'template_data',
        'template_checksum', 'quality_score', 'version_number', 'status',
        'source_terminal_id', 'terminal_meeting_session_id', 'enrollment_session_id', 'enrolled_by_user_id',
        'enrolled_at', 'superseded_at', 'revoked_at', 'revoked_by_user_id',
        'revoke_reason',
    ];

    protected $hidden = ['template_data'];

    protected $casts = [
        'quality_score' => 'decimal:3',
        'version_number' => 'integer',
        'enrolled_at' => 'datetime',
        'superseded_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function sourceTerminal(): BelongsTo { return $this->belongsTo(Terminal::class, 'source_terminal_id'); }
    public function terminalMeetingSession(): BelongsTo { return $this->belongsTo(TerminalMeetingSession::class, 'terminal_meeting_session_id'); }
    public function enrollmentSession(): BelongsTo { return $this->belongsTo(BiometricEnrollmentSession::class, 'enrollment_session_id'); }
    public function enrolledBy(): BelongsTo { return $this->belongsTo(User::class, 'enrolled_by_user_id'); }
    public function revokedBy(): BelongsTo { return $this->belongsTo(User::class, 'revoked_by_user_id'); }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE);
    }
}
