<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingScheduleVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'meeting_id', 'version_number', 'meeting_date', 'start_time', 'end_time',
        'location', 'venue_type', 'venue_id', 'venue_snapshot', 'source_reschedule_case_id', 'created_by_user_id', 'created_at',
    ];

    protected $casts = ['meeting_date' => 'date', 'venue_snapshot' => 'array'];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function venue(): BelongsTo { return $this->belongsTo(MeetingVenue::class, 'venue_id'); }
    public function sourceRescheduleCase(): BelongsTo { return $this->belongsTo(MeetingRescheduleCase::class, 'source_reschedule_case_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
