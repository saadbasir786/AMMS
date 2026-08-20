<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ReportExportResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'uuid' => $this->uuid,
            'report_type' => $this->report_type,
            'format' => $this->format,
            'status' => $this->status,
            'definition_version' => $this->definition_version,
            'requested_at' => $this->requested_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'expires_at' => $this->expires_at,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'checksum' => $this->checksum,
            'download_available' => $this->status === 'COMPLETED' && (!$this->expires_at || $this->expires_at->isFuture()),
            'error_code' => $this->error_code,
        ];
    }
}
