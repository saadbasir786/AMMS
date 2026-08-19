<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingScheduleProposalAcceptanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_schedule_proposal_id' => $this->meeting_schedule_proposal_id,
            'organizational_unit_id' => $this->organizational_unit_id,
            'unit' => new OrganizationalUnitResource($this->whenLoaded('unit')),
            'accepted_by' => $this->accepted_by,
            'accepted_at' => $this->accepted_at,
            'created_at' => $this->created_at,
        ];
    }
}
