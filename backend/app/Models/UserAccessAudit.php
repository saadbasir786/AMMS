<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAccessAudit extends Model
{
    protected $fillable = ['user_id', 'actor_id', 'action', 'role', 'organizational_unit_id', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id'); }
}
