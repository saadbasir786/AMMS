<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalPairingCode extends Model
{
    protected $fillable = [
        'terminal_id', 'code_hash', 'expires_at', 'consumed_at', 'issued_by_user_id',
    ];

    protected $hidden = ['code_hash'];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    public function terminal(): BelongsTo { return $this->belongsTo(Terminal::class); }
    public function issuedBy(): BelongsTo { return $this->belongsTo(User::class, 'issued_by_user_id'); }
}
