<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmmsRoleScope extends Model
{
    protected $fillable = ['user_id','role_id','organizational_unit_id'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function role(): BelongsTo { return $this->belongsTo(\Spatie\Permission\Models\Role::class); }
    public function unit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id'); }
}
