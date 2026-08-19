<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingScheduleVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'meeting_id', 'version_number', 'meeting_date', 'start_time', 'end_time',
        'location', 'source_reschedule_case_id', 'created_by_user_id', 'created_at',
    ];

    protected $casts = ['meeting_date' => 'date'];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function sourceRescheduleCase(): BelongsTo { return $this->belongsTo(MeetingRescheduleCase::class, 'source_reschedule_case_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
