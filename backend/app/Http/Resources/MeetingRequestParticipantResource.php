<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class MeetingRequestParticipantResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->id,'participant_type'=>$this->participant_type,'employee'=>new EmployeeResource($this->whenLoaded('employee')),'created_at'=>$this->created_at]; } }