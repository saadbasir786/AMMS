<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingActionItemExtensionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'action_item_id' => $this->action_item_id,
            'current_due_date' => $this->current_due_date?->toDateString(),
            'requested_due_date' => $this->requested_due_date?->toDateString(),
            'reason' => $this->reason,
            'status' => $this->status,
            'requested_by_user_id' => $this->requested_by_user_id,
            'requested_at' => $this->requested_at,
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'reviewed_at' => $this->reviewed_at,
            'review_reason' => $this->review_reason,
        ];
    }
}
