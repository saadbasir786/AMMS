<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organizational_unit_id' => $this->organizational_unit_id,
            'title' => $this->title,
            'code' => $this->code,
            'position_type' => $this->position_type,
            'seniority_level' => $this->seniority_level,
            'status' => $this->status,
            'employees_count' => $this->employees_count ?? null,
            'unit' => new OrganizationalUnitResource($this->whenLoaded('unit')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
