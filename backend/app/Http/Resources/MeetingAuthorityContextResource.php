<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingAuthorityContextResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'organization_authority_id' => $this->organization_authority_id,
            'authority_type' => $this->authority_type,
            'authority_position_id' => $this->authority_position_id,
            'authority_employee_id' => $this->authority_employee_id,
            'authority_office_unit_id' => $this->authority_office_unit_id,
            'authority_position' => new PositionResource($this->whenLoaded('authorityPosition')),
            'authority_employee' => new EmployeeResource($this->whenLoaded('authorityEmployee')),
            'authority_office_unit' => new OrganizationalUnitResource($this->whenLoaded('authorityOfficeUnit')),
        ];
    }
}
