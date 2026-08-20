<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MeetingExceptionCaseResource extends JsonResource
{
    public function toArray($request): array
    {
        $version = $this->currentVersion;
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'meeting_id' => $this->meeting_id,
            'case_type' => $this->case_type,
            'status' => $this->status,
            'governance_mode' => $this->governance_mode,
            'baseline' => [
                'meeting_status' => $this->baseline_meeting_status,
                'schedule_version_id' => $this->baseline_schedule_version_id,
                'participant_version_id' => $this->baseline_participant_version_id,
            ],
            'current_version' => $version ? [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'reason' => $version->reason,
                'administrative_note' => $version->administrative_note,
                'replacement_expected' => (bool) $version->replacement_expected,
                'proposed_resume_date' => $version->proposed_resume_date?->toDateString(),
                'submitted_at' => $version->submitted_at?->toISOString(),
            ] : null,
            'reviews' => MeetingExceptionReviewResource::collection($version?->reviews ?? collect()),
            'applied_at' => $this->applied_at?->toISOString(),
            'withdrawn_at' => $this->withdrawn_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
