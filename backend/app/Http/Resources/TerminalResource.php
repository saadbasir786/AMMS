<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerminalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'organization' => $this->whenLoaded('organization'),
            'code' => $this->code,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'platform' => $this->platform,
            'os_version' => $this->os_version,
            'app_version' => $this->app_version,
            'status' => $this->status,
            'last_seen_at' => $this->last_seen_at,
            'registered_at' => $this->registered_at,
            'current_session_id' => $this->current_session_id,
            'active_credential' => $this->when(isset($this->active_credential), fn () => $this->active_credential ? [
                'id' => $this->active_credential->id,
                'issued_at' => $this->active_credential->issued_at,
                'expires_at' => $this->active_credential->expires_at,
                'revoked_at' => $this->active_credential->revoked_at,
            ] : null),
        ];
    }
}
