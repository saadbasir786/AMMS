<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class MeetingRequestAction extends Model
{
    public $timestamps = false;

    protected $fillable = ['meeting_request_id', 'actor_id', 'action', 'from_status', 'to_status', 'comment', 'metadata', 'created_at'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(static fn (): never => throw new LogicException('Meeting request audit history is immutable.'));
        static::deleting(static fn (): never => throw new LogicException('Meeting request audit history is immutable.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
