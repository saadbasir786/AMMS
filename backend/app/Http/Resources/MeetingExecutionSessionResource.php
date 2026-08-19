<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingExecutionSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'meeting_id' => $this->meeting_id,
            'schedule_version_id' => $this->schedule_version_id,
            'participant_version_id' => $this->participant_version_id,
            'status' => $this->status,
            'started_at' => $this->started_at,
            'started_by_user_id' => $this->started_by_user_id,
            'ended_at' => $this->ended_at,
            'ended_by_user_id' => $this->ended_by_user_id,
            'start_notes' => $this->start_notes,
            'end_notes' => $this->end_notes,
        ];
    }
}
