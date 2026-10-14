<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerminalMeetingParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->employee;

        return [
            'employee_id' => $employee?->id,
            'employee_number' => $employee?->employee_number,
            'first_name' => $employee?->first_name,
            'last_name' => $employee?->last_name,
            'job_title' => $employee?->position?->title,
            'attendance_status' => $this->attendance_exists ? 'PRESENT' : 'NOT_CHECKED_IN',
        ];
    }
}
