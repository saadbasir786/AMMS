<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingActionItemAssignmentHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'action_item_id' => $this->action_item_id,
            'from_unit_id' => $this->from_unit_id,
            'to_unit_id' => $this->to_unit_id,
            'from_employee_id' => $this->from_employee_id,
            'to_employee_id' => $this->to_employee_id,
            'reason' => $this->reason,
            'changed_by_user_id' => $this->changed_by_user_id,
            'changed_at' => $this->changed_at,
            'from_unit' => $this->whenLoaded('fromUnit', fn () => $this->fromUnit ? ['id' => $this->fromUnit->id, 'name' => $this->fromUnit->name] : null),
            'to_unit' => $this->whenLoaded('toUnit', fn () => $this->toUnit ? ['id' => $this->toUnit->id, 'name' => $this->toUnit->name] : null),
            'from_employee' => $this->whenLoaded('fromEmployee', fn () => $this->fromEmployee ? ['id' => $this->fromEmployee->id, 'full_name' => $this->fromEmployee->full_name] : null),
            'to_employee' => $this->whenLoaded('toEmployee', fn () => $this->toEmployee ? ['id' => $this->toEmployee->id, 'full_name' => $this->toEmployee->full_name] : null),
        ];
    }
}
