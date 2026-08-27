<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingMinuteReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reviewer_type' => $this->reviewer_type,
            'organizational_unit_id' => $this->organizational_unit_id,
            'authority_employee_id' => $this->authority_employee_id,
            'unit' => new OrganizationalUnitResource($this->whenLoaded('organizationalUnit')),
            'status' => $this->status,
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'reason' => $this->reason,
            'reviewed_at' => $this->reviewed_at,
        ];
    }
}
