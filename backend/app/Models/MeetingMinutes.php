<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeetingMinutes extends \Illuminate\Database\Eloquent\Model
{
    public const DRAFT = 'DRAFT';
    public const UNDER_REVIEW = 'UNDER_REVIEW';
    public const RETURNED_FOR_CORRECTION = 'RETURNED_FOR_CORRECTION';
    public const READY_TO_FINALIZE = 'READY_TO_FINALIZE';
    public const FINALIZED = 'FINALIZED';

    protected $fillable = ['uuid', 'meeting_id', 'status', 'current_version_id', 'created_by_user_id', 'finalized_at', 'finalized_by_user_id'];
    protected $casts = ['finalized_at' => 'datetime'];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function currentVersion(): BelongsTo { return $this->belongsTo(MeetingMinuteVersion::class, 'current_version_id'); }
    public function versions(): HasMany { return $this->hasMany(MeetingMinuteVersion::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function finalizedBy(): BelongsTo { return $this->belongsTo(User::class, 'finalized_by_user_id'); }
    public function document(): HasOne { return $this->hasOne(MeetingMinuteDocument::class); }
}
