<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class AmmsRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->role;

        return [
            'id' => $role?->id ?? $this->role_id,
            'name' => $role?->name,
            'label' => $this->label,
            'description' => $this->description,
            'role_kind' => $this->role_kind,
            'status' => $this->status,
            'permissions' => $role?->permissions?->pluck('name')->values()->all() ?? [],
            'assigned_user_count' => isset($this->assigned_user_count) ? (int) $this->assigned_user_count : (int) DB::table('model_has_roles')->where('role_id', $role?->id ?? $this->role_id)->where('model_type', \App\Models\User::class)->count(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
