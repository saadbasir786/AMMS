<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BiometricEnrollmentSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'employee_id' => $this->employee_id,
            'terminal_id' => $this->terminal_id,
            'status' => $this->status,
            'required_finger_count' => $this->required_finger_count,
            'active_finger_count' => $this->whenLoaded('employee', fn () => $this->employee->biometricTemplates()->active()->distinct('finger_position')->count('finger_position')),
            'started_at' => $this->started_at,
            'expires_at' => $this->expires_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
            'cancel_reason' => $this->cancel_reason,
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'employee_number' => $this->employee->employee_number,
                'full_name' => $this->employee->full_name,
            ]),
        ];
    }
}
