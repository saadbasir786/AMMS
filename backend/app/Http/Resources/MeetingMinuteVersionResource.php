<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingMinuteVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'version_number' => $this->version_number,
            'execution_session_id' => $this->execution_session_id,
            'schedule_version_id' => $this->schedule_version_id,
            'participant_version_id' => $this->participant_version_id,
            'summary' => $this->summary,
            'general_notes' => $this->general_notes,
            'prepared_by_user_id' => $this->prepared_by_user_id,
            'submitted_at' => $this->submitted_at,
            'items' => MeetingMinuteVersionItemResource::collection($this->whenLoaded('items')),
            'decisions' => MeetingMinuteDecisionResource::collection($this->whenLoaded('decisions')),
            'attendance' => MeetingMinuteAttendanceSnapshotResource::collection($this->whenLoaded('attendanceSnapshots')),
            'reviews' => MeetingMinuteReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
