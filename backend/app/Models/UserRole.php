<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class UserRole extends Model { protected $fillable = ['user_id','role','organizational_unit_id']; public function user(): BelongsTo { return $this->belongsTo(User::class); } public function unit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id'); } }