<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRequestUnitParticipantConfirmationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_request_id' => $this->meeting_request_id,
            'organizational_unit_id' => $this->organizational_unit_id,
            'unit' => new OrganizationalUnitResource($this->whenLoaded('unit')),
            'confirmed_by' => $this->confirmed_by,
            'confirmed_at' => $this->confirmed_at,
            'created_at' => $this->created_at,
        ];
    }
}
