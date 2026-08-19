<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingMinuteDocument extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = [
        'uuid', 'meeting_minutes_id', 'meeting_minute_version_id', 'file_name',
        'mime_type', 'content_sha256', 'generated_at', 'generated_by_user_id',
    ];
    protected $casts = ['generated_at' => 'datetime'];

    public function minutes(): BelongsTo { return $this->belongsTo(MeetingMinutes::class, 'meeting_minutes_id'); }
    public function version(): BelongsTo { return $this->belongsTo(MeetingMinuteVersion::class, 'meeting_minute_version_id'); }
    public function generatedBy(): BelongsTo { return $this->belongsTo(User::class, 'generated_by_user_id'); }
}
