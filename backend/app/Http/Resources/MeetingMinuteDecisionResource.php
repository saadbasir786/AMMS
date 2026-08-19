<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingMinuteDecisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'sequence_number' => $this->sequence_number,
            'decision_number' => $this->decision_number,
            'decision_text' => $this->decision_text,
            'responsible_unit_id' => $this->responsible_unit_id,
            'responsible_employee_id' => $this->responsible_employee_id,
            'target_date' => $this->target_date,
            'notes' => $this->notes,
        ];
    }
}
