<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingAuthorityScheduleUnitAcknowledgementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_request_id' => $this->meeting_request_id,
            'meeting_schedule_proposal_id' => $this->meeting_schedule_proposal_id,
            'organizational_unit_id' => $this->organizational_unit_id,
            'unit' => new OrganizationalUnitResource($this->whenLoaded('unit')),
            'acknowledged_by' => $this->acknowledged_by,
            'acknowledged_at' => $this->acknowledged_at,
        ];
    }
}
