<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingScheduleProposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'meeting_request_id' => $this->meeting_request_id, 'proposed_by_unit_id' => $this->proposed_by_unit_id, 'proposed_by_unit' => new OrganizationalUnitResource($this->whenLoaded('proposedByUnit')), 'proposed_by_user_id' => $this->proposed_by_user_id, 'meeting_date' => $this->meeting_date?->toDateString(), 'start_time' => $this->start_time, 'end_time' => $this->end_time, 'location' => $this->location, 'comment' => $this->comment, 'status' => $this->status, 'proposal_type' => $this->proposal_type, 'acceptances' => MeetingScheduleProposalAcceptanceResource::collection($this->whenLoaded('acceptances')), 'authority_acknowledgements' => MeetingAuthorityScheduleAcknowledgementResource::collection($this->whenLoaded('authorityAcknowledgements')), 'created_at' => $this->created_at, 'updated_at' => $this->updated_at];
    }
}
