<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingMinuteVersionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'sequence_number' => $this->sequence_number, 'agenda_title' => $this->agenda_title, 'discussion_summary' => $this->discussion_summary];
    }
}
