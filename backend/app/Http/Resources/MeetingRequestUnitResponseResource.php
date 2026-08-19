<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRequestUnitResponseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'meeting_request_id' => $this->meeting_request_id,
            'directive_version_id' => $this->meeting_request_directive_version_id,
            'organizational_unit_id' => $this->organizational_unit_id, 'response_status' => $this->response_status,
            'coordination_started_by' => $this->coordination_started_by, 'coordination_started_at' => $this->coordination_started_at,
            'acknowledged_by' => $this->acknowledged_by, 'acknowledged_at' => $this->acknowledged_at,
            'unit' => new OrganizationalUnitResource($this->whenLoaded('unit')),
            'coordination_starter' => new UserResource($this->whenLoaded('coordinationStarter')),
            'acknowledger' => new UserResource($this->whenLoaded('acknowledger')),
        ];
    }
}
