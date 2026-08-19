<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id'=>$this->id,'organizational_unit_id'=>$this->organizational_unit_id,'title'=>$this->title,'position_type'=>$this->position_type,'status'=>$this->status];
    }
}
