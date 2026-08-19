<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingTerminalAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $staleness = $this->relationLoaded('meeting') && $this->meeting ? $this->staleness($this->meeting) : ['schedule_changed' => false, 'participants_changed' => false];
        $effectiveStatus = ($staleness['schedule_changed'] || $staleness['participants_changed']) && $this->status === 'ASSIGNED' ? 'STALE' : $this->status;
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'meeting_id' => $this->meeting_id,
            'terminal_id' => $this->terminal_id,
            'terminal' => new TerminalResource($this->whenLoaded('terminal')),
            'baseline_schedule_version_id' => $this->baseline_schedule_version_id,
            'baseline_participant_version_id' => $this->baseline_participant_version_id,
            'valid_from' => $this->valid_from,
            'valid_until' => $this->valid_until,
            'status' => $effectiveStatus,
            'is_stale' => $staleness['schedule_changed'] || $staleness['participants_changed'],
            'schedule_changed' => $staleness['schedule_changed'],
            'participants_changed' => $staleness['participants_changed'],
            'refreshed_at' => $this->refreshed_at,
            'revoked_at' => $this->revoked_at,
            'revoke_reason' => $this->revoke_reason,
            'active_session' => $this->whenLoaded('sessions', fn () => $this->sessions->first(fn ($session) => $session->isEffectivelyActive()) ? new TerminalMeetingSessionResource($this->sessions->first(fn ($session) => $session->isEffectivelyActive())) : null),
        ];
    }
}
