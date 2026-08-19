<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class MeetingRequestAttachment extends Model { protected $fillable = ['meeting_request_id','original_name','stored_path','mime_type','size','uploaded_by']; public function request(): BelongsTo { return $this->belongsTo(MeetingRequest::class, 'meeting_request_id'); } }