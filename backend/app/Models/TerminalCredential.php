<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalCredential extends Model
{
    protected $fillable = [
        'terminal_id', 'token_hash', 'issued_at', 'expires_at', 'last_used_at',
        'revoked_at', 'created_by_user_id',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function terminal(): BelongsTo { return $this->belongsTo(Terminal::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
