<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRequestParticipantNominationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'meeting_request_id' => $this->meeting_request_id, 'employee_id' => $this->employee_id, 'employee' => new EmployeeResource($this->whenLoaded('employee')), 'organizational_unit_id' => $this->organizational_unit_id, 'nominated_by_unit_id' => $this->nominated_by_unit_id, 'nominated_by_user_id' => $this->nominated_by_user_id, 'participant_stage' => $this->participant_stage, 'nomination_status' => $this->nomination_status, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
