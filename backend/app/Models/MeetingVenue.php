<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingVenue extends Model
{
    protected $fillable = ['organization_id', 'name', 'description', 'location_details', 'capacity', 'status'];

    protected $casts = ['capacity' => 'integer'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function meetings(): HasMany { return $this->hasMany(Meeting::class, 'venue_id'); }
    public function scheduleVersions(): HasMany { return $this->hasMany(MeetingScheduleVersion::class, 'venue_id'); }
}
