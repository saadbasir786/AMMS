<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAction extends Model
{
    public $timestamps = false;

    protected $fillable = ['meeting_id', 'actor_id', 'action', 'comment', 'metadata', 'created_at'];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function meeting(): BelongsTo { return $this->belongsTo(Meeting::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
