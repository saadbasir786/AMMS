<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingDecisionFollowUpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $items = $this->relationLoaded('actionItems') ? $this->actionItems : collect();
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'meeting_id' => $this->meeting_id,
            'meeting_minutes_id' => $this->meeting_minutes_id,
            'meeting_minute_version_id' => $this->meeting_minute_version_id,
            'decision_id' => $this->meeting_decision_id,
            'decision_number' => $this->decision_number,
            'decision' => $this->whenLoaded('decision', fn () => [
                'id' => $this->decision->id,
                'decision_number' => $this->decision->decision_number,
                'decision_text' => $this->decision->decision_text,
                'responsible_unit_id' => $this->decision->responsible_unit_id,
                'responsible_employee_id' => $this->decision->responsible_employee_id,
                'target_date' => $this->decision->target_date?->toDateString(),
                'notes' => $this->decision->notes,
            ]),
            'classification' => $this->classification,
            'status' => $this->status,
            'classified_by_user_id' => $this->classified_by_user_id,
            'classified_at' => $this->classified_at,
            'no_action_reason' => $this->no_action_reason,
            'action_item_count' => $items->count(),
            'open_count' => $items->where('status', 'OPEN')->count(),
            'in_progress_count' => $items->where('status', 'IN_PROGRESS')->count(),
            'blocked_count' => $items->where('status', 'BLOCKED')->count(),
            'pending_verification_count' => $items->where('status', 'PENDING_VERIFICATION')->count(),
            'completed_count' => $items->where('status', 'COMPLETED')->count(),
            'cancelled_count' => $items->where('status', 'CANCELLED')->count(),
            'overdue_count' => $items->where('is_overdue', true)->count(),
            'action_items' => MeetingActionItemResource::collection($this->whenLoaded('actionItems')),
            'created_at' => $this->created_at,
        ];
    }
}
