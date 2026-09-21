<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAgendaItem extends \Illuminate\Database\Eloquent\Model
{
    protected $fillable = ['meeting_id', 'sequence_number', 'title', 'description'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
