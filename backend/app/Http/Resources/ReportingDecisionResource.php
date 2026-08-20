<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportingDecisionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'decision_number' => $this->decision_number,
            'decision_text' => $this->decision_text,
            'minute_version_id' => $this->meeting_minute_version_id,
            'responsible_unit' => $this->responsibleUnit?->only(['id', 'name', 'code']),
            'responsible_employee' => $this->responsibleEmployee?->only(['id', 'full_name', 'employee_number']),
            'target_date' => $this->target_date?->toDateString(),
            'meeting' => $this->version?->minutes?->meeting?->only(['id', 'meeting_number', 'title']),
        ];
    }
}
