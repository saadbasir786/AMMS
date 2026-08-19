<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingMinuteAttendanceSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->employee_id,
            'organizational_unit_id' => $this->organizational_unit_id,
            'participant_role' => $this->participant_role,
            'display_name' => $this->display_name,
            'attendance_status' => $this->attendance_status,
        ];
    }
}
