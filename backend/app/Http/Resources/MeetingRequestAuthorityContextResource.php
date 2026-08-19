<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRequestAuthorityContextResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_authority_id' => $this->organization_authority_id,
            'authority_type' => $this->authority_type,
            'authority_office_unit_id' => $this->authority_office_unit_id,
            'authority_position_id' => $this->authority_position_id,
            'authority_employee_id' => $this->authority_employee_id,
            'office_review_status' => $this->office_review_status,
            'office_reviewed_by' => $this->office_reviewed_by,
            'office_reviewed_at' => $this->office_reviewed_at,
            'office_review_comment' => $this->office_review_comment,
            'forwarded_by' => $this->forwarded_by,
            'forwarded_at' => $this->forwarded_at,
            'authority_decision_status' => $this->authority_decision_status,
            'authority_decided_by' => $this->authority_decided_by,
            'authority_decided_at' => $this->authority_decided_at,
            'authority_decision_comment' => $this->authority_decision_comment,
            'authority_office_unit' => new OrganizationalUnitResource($this->whenLoaded('authorityOfficeUnit')),
            'authority_position' => new PositionResource($this->whenLoaded('authorityPosition')),
            'authority_employee' => new EmployeeResource($this->whenLoaded('authorityEmployee')),
        ];
    }
}
