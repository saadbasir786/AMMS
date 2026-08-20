<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportingExceptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'meeting_id' => $this->meeting_id,
            'meeting_number' => $this->meeting?->meeting_number,
            'case_type' => $this->case_type,
            'status' => $this->status,
            'version' => $this->currentVersion?->version_number,
            'reason' => $this->currentVersion?->reason,
            'applied_at' => $this->applied_at,
            'baseline_schedule_version_id' => $this->baseline_schedule_version_id,
            'baseline_participant_version_id' => $this->baseline_participant_version_id,
            'review_summary' => $this->currentVersion?->reviews?->map(fn ($review) => ['reviewer_type' => $review->reviewer_type, 'unit_id' => $review->organizational_unit_id, 'status' => $review->status])->values(),
        ];
    }
}
