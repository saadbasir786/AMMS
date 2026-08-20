<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportingAttendanceResource extends JsonResource
{
    public function toArray($request): array
    {
        $detail = $request->user()?->can('reporting.attendance.detail') === true;
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'meeting_number' => $this->meeting?->meeting_number,
            'official' => $this->invalidation === null,
            'attendance_method' => $this->attendance_method,
            'recorded_at_server' => $this->recorded_at_server,
            'invalidation' => $this->invalidation ? ['id' => $this->invalidation->id, 'reason' => $this->invalidation->reason, 'invalidated_at' => $this->invalidation->invalidated_at] : null,
            'employee' => $detail ? $this->employee?->only(['id', 'full_name', 'employee_number']) : null,
            'organizational_unit' => $detail ? $this->employee?->unit?->only(['id', 'name', 'code']) : null,
        ];
    }
}
