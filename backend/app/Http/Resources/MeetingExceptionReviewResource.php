<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\MeetingExceptionActionCapabilities;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingExceptionReviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'action_capabilities' => $request->user()
                ? app(MeetingExceptionActionCapabilities::class)->forReview($this->resource, $request->user()) : [],
            'unit_name' => $this->organizationalUnit?->name,
            'uuid' => $this->uuid,
            'exception_case_version_id' => $this->exception_case_version_id,
            'reviewer_type' => $this->reviewer_type,
            'organizational_unit_id' => $this->organizational_unit_id,
            'authority_employee_id' => $this->authority_employee_id,
            'review_requirement' => $this->review_requirement,
            'status' => $this->status,
            'reviewed_by_user_id' => $this->reviewed_by_user_id,
            'reason' => $this->reason,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
        ];
    }
}
