<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingRequestUnitReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_request_id' => $this->meeting_request_id,
            'organizational_unit_id' => $this->organizational_unit_id,
            'unit' => new OrganizationalUnitResource($this->whenLoaded('unit')),
            'review_status' => $this->review_status,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at,
            'comment' => $this->comment,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
