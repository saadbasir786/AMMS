<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $roleName = $this->role?->name ?? $this->role;
        $unit = $this->relationLoaded('unit') ? $this->unit : null;

        return [
            'id' => $this->id,
            'role' => $roleName,
            'organizational_unit_id' => $this->organizational_unit_id,
            'unit' => $unit ? new OrganizationalUnitResource($unit) : null,
        ];
    }
}
