<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MeetingRequestParticipant extends Model { protected $fillable = ['meeting_request_id','employee_id','participant_type','created_by']; public function employee(): BelongsTo { return $this->belongsTo(Employee::class); } public function request(): BelongsTo { return $this->belongsTo(MeetingRequest::class, 'meeting_request_id'); } }