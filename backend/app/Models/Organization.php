<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['name', 'short_name', 'type', 'status'];

    public function units(): HasMany
    {
        return $this->hasMany(OrganizationalUnit::class);
    }

    public function authorities(): HasMany
    {
        return $this->hasMany(OrganizationAuthority::class);
    }
}
