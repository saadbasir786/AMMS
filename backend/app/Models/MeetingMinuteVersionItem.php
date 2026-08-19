<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingMinuteVersionItem extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = ['meeting_minute_version_id', 'sequence_number', 'agenda_title', 'discussion_summary'];
    public function version(): BelongsTo { return $this->belongsTo(MeetingMinuteVersion::class, 'meeting_minute_version_id'); }
}
