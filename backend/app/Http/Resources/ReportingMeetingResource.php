<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportingMeetingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'meeting_number' => $this->meeting_number,
            'title' => $this->title,
            'originating_flow_type' => $this->request?->flow_type,
            'requesting_unit' => $this->organizingUnit?->only(['id', 'name', 'code']),
            'participating_units' => $this->units?->map(fn ($unit) => ['id' => $unit->organizational_unit_id, 'name' => $unit->unit?->name, 'role' => $unit->role])->values(),
            'high_authority' => $this->authorityContext !== null,
            'status' => $this->status,
            'schedule' => $this->currentScheduleVersion ? [
                'version_id' => $this->currentScheduleVersion->id,
                'version_number' => $this->currentScheduleVersion->version_number,
                'meeting_date' => $this->currentScheduleVersion->meeting_date?->toDateString(),
                'start_time' => $this->currentScheduleVersion->start_time,
                'end_time' => $this->currentScheduleVersion->end_time,
                'location' => $this->currentScheduleVersion->location,
            ] : null,
            'participant_count' => $this->currentParticipantVersion?->members?->unique('employee_id')->count() ?? 0,
            'created_at' => $this->created_at,
        ];
    }
}
