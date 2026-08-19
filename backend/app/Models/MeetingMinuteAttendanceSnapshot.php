<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingMinuteAttendanceSnapshot extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'meeting_minute_version_id', 'employee_id', 'organizational_unit_id',
        'participant_role', 'display_name', 'attendance_status',
    ];

    public function version(): BelongsTo { return $this->belongsTo(MeetingMinuteVersion::class, 'meeting_minute_version_id'); }
    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
    public function unit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id'); }
}
