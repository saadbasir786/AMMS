<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportingActionItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'action_number' => $this->action_number,
            'title' => $this->title,
            'status' => $this->status,
            'progress_percent' => $this->progress_percent,
            'responsible_unit' => $this->responsibleUnit?->only(['id', 'name', 'code']),
            'assigned_employee' => $this->assignedEmployee?->only(['id', 'full_name', 'employee_number']),
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->is_overdue,
            'completed_at' => $this->completed_at,
            'extension_state' => $this->extensionRequests?->sortByDesc('created_at')->first()?->status,
            'evidence_count' => $this->evidence?->count() ?? 0,
        ];
    }
}
