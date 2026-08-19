<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'meeting_id' => $this->meeting_id, 'organizational_unit_id' => $this->organizational_unit_id, 'role' => $this->role, 'unit' => new OrganizationalUnitResource($this->whenLoaded('unit'))];
    }
}
