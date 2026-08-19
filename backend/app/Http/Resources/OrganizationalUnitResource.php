<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class OrganizationalUnitResource extends JsonResource { public function toArray(Request $request): array { return ['id'=>$this->id,'organization_id'=>$this->organization_id,'parent_id'=>$this->parent_id,'name'=>$this->name,'code'=>$this->code,'unit_type'=>$this->unit_type,'status'=>$this->status,'children'=>self::collection($this->whenLoaded('children'))]; } }