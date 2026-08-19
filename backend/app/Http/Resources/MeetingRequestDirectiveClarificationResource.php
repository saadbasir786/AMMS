<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRequestDirectiveClarificationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'directive_version_id' => $this->meeting_request_directive_version_id,
            'organizational_unit_id' => $this->organizational_unit_id, 'requested_by' => $this->requested_by,
            'requested_at' => $this->requested_at, 'reason' => $this->reason,
            'responded_by' => $this->responded_by, 'responded_at' => $this->responded_at, 'response' => $this->response,
            'unit' => new OrganizationalUnitResource($this->whenLoaded('unit')),
            'requester' => new UserResource($this->whenLoaded('requester')),
            'responder' => new UserResource($this->whenLoaded('responder')),
        ];
    }
}
