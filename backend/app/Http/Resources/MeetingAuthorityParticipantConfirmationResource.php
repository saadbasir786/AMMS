<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingAuthorityParticipantConfirmationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meeting_request_id' => $this->meeting_request_id,
            'side' => $this->side,
            'confirmed_by' => $this->confirmed_by,
            'confirmed_at' => $this->confirmed_at,
            'confirmed_user' => new UserResource($this->whenLoaded('confirmedBy')),
        ];
    }
}
