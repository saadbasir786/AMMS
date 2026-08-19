<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'meeting_id' => $this->meeting_id,
            'employee_id' => $this->employee_id,
            'participant_version_id' => $this->participant_version_id,
            'terminal_id' => $this->terminal_id,
            'terminal_session_id' => $this->terminal_session_id,
            'attendance_method' => $this->attendance_method,
            'finger_position' => $this->finger_position,
            'matcher_vendor' => $this->matcher_vendor,
            'matcher_algorithm_version' => $this->matcher_algorithm_version,
            'match_score' => $this->match_score,
            'threshold_reference' => $this->threshold_reference,
            'recorded_at_server' => $this->recorded_at_server,
            'device_captured_at' => $this->device_captured_at,
            'recorded_by_user_id' => $this->recorded_by_user_id,
            'manual_reason' => $this->manual_reason,
        ];
    }
}
