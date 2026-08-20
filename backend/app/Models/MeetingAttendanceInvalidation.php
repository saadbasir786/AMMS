<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttendanceInvalidation extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;
    public const POSTPONED = 'MEETING_POSTPONED';
    public const CANCELLED = 'MEETING_CANCELLED';

    protected $table = 'meeting_attendance_invalidations';
    protected $fillable = [
        'uuid', 'meeting_attendance_record_id', 'meeting_id', 'exception_case_id',
        'reason', 'invalidated_at', 'invalidated_by_user_id', 'created_at',
    ];
    protected $casts = ['invalidated_at' => 'datetime', 'created_at' => 'datetime'];

    public function attendanceRecord(): BelongsTo { return $this->belongsTo(MeetingAttendanceRecord::class, 'meeting_attendance_record_id'); }
    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function exceptionCase(): BelongsTo { return $this->belongsTo(MeetingExceptionCase::class, 'exception_case_id'); }
    public function invalidatedBy(): BelongsTo { return $this->belongsTo(User::class, 'invalidated_by_user_id'); }
}
