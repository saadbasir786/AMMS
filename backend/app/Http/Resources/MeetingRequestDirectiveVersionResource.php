<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRequestDirectiveVersionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'version' => $this->version, 'version_status' => $this->version_status,
            'subject' => $this->subject, 'purpose' => $this->purpose, 'description' => $this->description,
            'directive' => $this->directive, 'meeting_justification' => $this->meeting_justification,
            'requested_outcome' => $this->requested_outcome, 'priority' => $this->priority,
            'preferred_date' => $this->preferred_date?->toDateString(), 'preferred_start_time' => $this->preferred_start_time,
            'estimated_duration_minutes' => $this->estimated_duration_minutes, 'preferred_location' => $this->preferred_location,
            'proposed_agenda' => $this->proposed_agenda, 'participating_unit_ids' => $this->participating_unit_ids,
            'prepared_by' => $this->prepared_by, 'issued_by' => $this->issued_by, 'authority_employee_id' => $this->authority_employee_id,
            'authority_position_id' => $this->authority_position_id, 'issued_at' => $this->issued_at, 'is_current' => $this->is_current,
            'prepared_by_user' => new UserResource($this->whenLoaded('preparedBy')),
            'issuer' => new UserResource($this->whenLoaded('issuer')),
        ];
    }
}
