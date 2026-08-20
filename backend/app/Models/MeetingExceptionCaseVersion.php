<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingExceptionCaseVersion extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;
    protected $table = 'meeting_exception_case_versions';
    protected $fillable = [
        'uuid', 'exception_case_id', 'version_number', 'reason', 'administrative_note',
        'replacement_expected', 'proposed_resume_date', 'baseline_meeting_status',
        'baseline_schedule_version_id', 'baseline_participant_version_id', 'prepared_by_user_id',
        'submitted_at', 'created_at',
    ];
    protected $casts = [
        'replacement_expected' => 'boolean',
        'proposed_resume_date' => 'date',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function exceptionCase(): BelongsTo { return $this->belongsTo(MeetingExceptionCase::class, 'exception_case_id'); }
    public function reviews(): HasMany { return $this->hasMany(MeetingExceptionReview::class, 'exception_case_version_id'); }
    public function preparedBy(): BelongsTo { return $this->belongsTo(User::class, 'prepared_by_user_id'); }
    public function baselineScheduleVersion(): BelongsTo { return $this->belongsTo(MeetingScheduleVersion::class, 'baseline_schedule_version_id'); }
    public function baselineParticipantVersion(): BelongsTo { return $this->belongsTo(MeetingParticipantVersion::class, 'baseline_participant_version_id'); }

    public function isSubmitted(): bool { return $this->submitted_at !== null; }
}
