<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Terminal extends Model
{
    protected $fillable = [
        'uuid', 'organization_id', 'code', 'name', 'serial_number', 'manufacturer',
        'model', 'platform', 'os_version', 'app_version', 'status', 'last_seen_at',
        'registered_by_user_id', 'registered_at', 'current_session_id',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'registered_at' => 'datetime',
    ];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function registeredBy(): BelongsTo { return $this->belongsTo(User::class, 'registered_by_user_id'); }
    public function credentials(): HasMany { return $this->hasMany(TerminalCredential::class); }
    public function activeCredential(): HasOne { return $this->hasOne(TerminalCredential::class)->whereNull('revoked_at')->latestOfMany('issued_at'); }
    public function assignments(): HasMany { return $this->hasMany(MeetingTerminalAssignment::class); }
    public function sessions(): HasMany { return $this->hasMany(TerminalMeetingSession::class); }
    public function currentSession(): BelongsTo { return $this->belongsTo(TerminalMeetingSession::class, 'current_session_id'); }
}
