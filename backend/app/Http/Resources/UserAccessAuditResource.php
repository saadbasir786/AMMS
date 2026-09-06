<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserAccessAuditResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'role' => $this->role,
            'organizational_unit_id' => $this->organizational_unit_id,
            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? new OrganizationalUnitResource($this->unit) : null),
            'actor' => $this->whenLoaded('actor', fn () => $this->actor ? ['id' => $this->actor->id, 'name' => $this->actor->name, 'email' => $this->actor->email] : null),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
