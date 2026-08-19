<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class Meeting extends Model { protected $fillable = ['meeting_request_id','meeting_number','title','description','organizing_unit_id','coordinator_id','chairperson_employee_id','meeting_date','start_time','end_time','location','agenda','status']; protected $casts = ['meeting_date'=>'date']; public function request(): BelongsTo { return $this->belongsTo(MeetingRequest::class, 'meeting_request_id'); } public function chairperson(): BelongsTo { return $this->belongsTo(Employee::class, 'chairperson_employee_id'); } public function participants(): HasMany { return $this->hasMany(MeetingParticipant::class); }
    public function units(): HasMany { return $this->hasMany(MeetingUnit::class); }
    public function authorityContext(): HasOne { return $this->hasOne(MeetingAuthorityContext::class); }
}