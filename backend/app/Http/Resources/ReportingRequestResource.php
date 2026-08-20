<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportingRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'subject' => $this->subject,
            'flow_type' => $this->flow_type,
            'requesting_unit' => $this->requestingUnit?->only(['id', 'name', 'code']),
            'priority' => $this->priority,
            'status' => $this->status,
            'meeting_id' => $this->meeting?->id,
            'created_at' => $this->created_at,
        ];
    }
}
