<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerminalMeetingSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'assignment_id' => $this->assignment_id,
            'terminal_id' => $this->terminal_id,
            'meeting_id' => $this->meeting_id,
            'schedule_version_id' => $this->schedule_version_id,
            'participant_version_id' => $this->participant_version_id,
            'status' => $this->isExpired() && $this->status === 'ACTIVE' ? 'EXPIRED' : $this->status,
            'activated_at' => $this->activated_at,
            'expires_at' => $this->expires_at,
            'closed_at' => $this->closed_at,
            'close_reason' => $this->close_reason,
        ];
    }
}
