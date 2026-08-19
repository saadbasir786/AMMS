<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class MeetingParticipantResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->id,'employee'=>new EmployeeResource($this->whenLoaded('employee')),'organizational_unit_id'=>$this->organizational_unit_id,'role'=>$this->role]; } }