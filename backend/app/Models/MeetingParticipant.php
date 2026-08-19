<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MeetingParticipant extends Model { protected $fillable = ['meeting_id','employee_id','organizational_unit_id','role']; public function employee(): BelongsTo { return $this->belongsTo(Employee::class); } }