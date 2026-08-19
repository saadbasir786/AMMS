<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingMinutesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'meeting_id' => $this->meeting_id,
            'status' => $this->status,
            'current_version_id' => $this->current_version_id,
            'created_by_user_id' => $this->created_by_user_id,
            'finalized_at' => $this->finalized_at,
            'finalized_by_user_id' => $this->finalized_by_user_id,
            'current_version' => new MeetingMinuteVersionResource($this->whenLoaded('currentVersion')),
            'versions' => MeetingMinuteVersionResource::collection($this->whenLoaded('versions')),
            'document' => $this->whenLoaded('document', fn (): ?array => $this->document ? [
                'uuid' => $this->document->uuid,
                'file_name' => $this->document->file_name,
                'mime_type' => $this->document->mime_type,
                'content_sha256' => $this->document->content_sha256,
                'generated_at' => $this->document->generated_at,
            ] : null),
        ];
    }
}
