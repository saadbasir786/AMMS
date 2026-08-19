<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Employee extends Model { protected $fillable = ['employee_number','first_name','last_name','father_name','organizational_unit_id','position_id','email','phone','status']; protected $appends = ['full_name']; public function getFullNameAttribute(): string { return trim($this->first_name.' '.$this->last_name); } public function unit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'organizational_unit_id'); } public function position(): BelongsTo { return $this->belongsTo(Position::class); }
    public function biometricTemplates(): HasMany { return $this->hasMany(EmployeeBiometricTemplate::class); } }