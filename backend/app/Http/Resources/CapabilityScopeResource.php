<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CapabilityScopeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'capability' => $this->capability,
            'organizational_unit_id' => $this->organizational_unit_id,
            'unit' => new OrganizationalUnitResource($this->whenLoaded('unit')),
            'granted_by_user_id' => $this->granted_by_user_id,
            'granted_at' => $this->granted_at?->toISOString(),
            'revoked_by_user_id' => $this->revoked_by_user_id,
            'revoked_at' => $this->revoked_at?->toISOString(),
            'active' => $this->revoked_at === null,
        ];
    }
}
