<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'father_name' => $this->father_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'organizational_unit_id' => $this->organizational_unit_id,
            'position_id' => $this->position_id,
            'unit_scope_ids' => $this->unitScopeIds(),
            'unit' => new OrganizationalUnitResource($this->whenLoaded('unit')),
            'position' => new PositionResource($this->whenLoaded('position')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function unitScopeIds(): array
    {
        $unit = $this->unit;
        $ids = [];
        $visited = [];

        while ($unit && !in_array((int) $unit->id, $visited, true)) {
            $visited[] = (int) $unit->id;
            $ids[] = (int) $unit->id;
            $unit = $unit->parent;
        }

        return $ids;
    }
}
