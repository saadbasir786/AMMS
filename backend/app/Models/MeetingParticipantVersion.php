<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingParticipantVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'meeting_id',
        'version_number',
        'source_change_case_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function sourceChangeCase(): BelongsTo
    {
        return $this->belongsTo(MeetingParticipantChangeCase::class, 'source_change_case_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(MeetingParticipantVersionMember::class, 'participant_version_id');
    }
}
