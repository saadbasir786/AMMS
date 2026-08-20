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
        ];
    }
}
