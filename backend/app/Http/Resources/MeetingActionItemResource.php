<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingActionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $followup = $this->relationLoaded('followup') ? $this->followup : null;
        $decision = $followup?->relationLoaded('decision') ? $followup->decision : null;
        $meeting = $followup?->relationLoaded('meeting') ? $followup->meeting : null;
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'action_number' => $this->action_number,
            'decision_followup_id' => $this->decision_followup_id,
            'meeting_id' => $followup?->meeting_id,
            'meeting_number' => $meeting?->meeting_number,
            'minutes_version_number' => $followup?->version?->version_number,
            'decision_id' => $followup?->meeting_decision_id,
            'decision_number' => $followup?->decision_number,
            'decision_text' => $decision?->decision_text,
            'title' => $this->title,
            'description' => $this->description,
            'responsible_unit' => $this->whenLoaded('responsibleUnit', fn () => ['id' => $this->responsibleUnit->id, 'name' => $this->responsibleUnit->name]),
            'assigned_employee' => $this->whenLoaded('assignedEmployee', fn () => $this->assignedEmployee ? ['id' => $this->assignedEmployee->id, 'name' => $this->assignedEmployee->full_name] : null),
            'status' => $this->status,
            'progress_percent' => (int) $this->progress_percent,
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => (bool) $this->is_overdue,
            'days_overdue' => (int) $this->days_overdue,
            'is_due_soon' => (bool) $this->is_due_soon,
            'started_at' => $this->started_at,
            'submitted_for_verification_at' => $this->submitted_for_verification_at,
            'submitted_by_user_id' => $this->submitted_by_user_id,
            'completed_at' => $this->completed_at,
            'completed_by_user_id' => $this->completed_by_user_id,
            'cancelled_at' => $this->cancelled_at,
            'cancelled_by_user_id' => $this->cancelled_by_user_id,
            'cancel_reason' => $this->cancel_reason,
            'updates' => MeetingActionItemUpdateResource::collection($this->whenLoaded('updates')),
            'extensions' => MeetingActionItemExtensionResource::collection($this->whenLoaded('extensionRequests')),
            'assignment_history' => MeetingActionItemAssignmentHistoryResource::collection($this->whenLoaded('assignmentHistory')),
            'evidence' => MeetingActionItemEvidenceResource::collection($this->whenLoaded('evidence')),
            'created_at' => $this->created_at,
        ];
    }
}
