<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BiometricTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'employee_id' => $this->employee_id,
            'modality' => $this->modality,
            'finger_position' => $this->finger_position,
            'template_vendor' => $this->template_vendor,
            'template_format' => $this->template_format,
            'algorithm_version' => $this->algorithm_version,
            'template_checksum' => $this->template_checksum,
            'quality_score' => $this->quality_score,
            'version_number' => $this->version_number,
            'status' => $this->status,
            'source_terminal_id' => $this->source_terminal_id,
            'enrollment_session_id' => $this->enrollment_session_id,
            'enrolled_at' => $this->enrolled_at,
            'superseded_at' => $this->superseded_at,
            'revoked_at' => $this->revoked_at,
            'revoke_reason' => $this->revoke_reason,
        ];
    }
}
