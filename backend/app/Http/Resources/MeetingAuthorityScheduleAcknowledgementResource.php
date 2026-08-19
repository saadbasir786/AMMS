<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingAuthorityScheduleAcknowledgementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_request_id' => $this->meeting_request_id,
            'meeting_schedule_proposal_id' => $this->meeting_schedule_proposal_id,
            'requesting_unit_id' => $this->requesting_unit_id,
            'acknowledged_by' => $this->acknowledged_by,
            'acknowledged_at' => $this->acknowledged_at,
            'requesting_unit' => new OrganizationalUnitResource($this->whenLoaded('requestingUnit')),
            'acknowledged_user' => new UserResource($this->whenLoaded('acknowledgedBy')),
        ];
    }
}
