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
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'employee' => $this->user->relationLoaded('employee') && $this->user->employee ? [
                    'full_name' => $this->user->employee->full_name,
                ] : null,
            ] : null),
            'granted_by_user_id' => $this->granted_by_user_id,
            'granted_at' => $this->granted_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'grant_reason' => $this->grant_reason,
            'revoked_by_user_id' => $this->revoked_by_user_id,
            'revoked_at' => $this->revoked_at?->toISOString(),
            'revocation_reason' => $this->revocation_reason,
            'active' => $this->isActive(),
        ];
    }
}
