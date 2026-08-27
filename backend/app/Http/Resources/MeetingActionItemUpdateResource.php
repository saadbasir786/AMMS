<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingActionItemUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'update_type' => $this->update_type,
            'progress_percent' => $this->progress_percent,
            'note' => $this->note,
            'created_by_user_id' => $this->created_by_user_id,
            'actor' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'created_at' => $this->created_at,
            'evidence' => MeetingActionItemEvidenceResource::collection($this->whenLoaded('evidence')),
        ];
    }
}
