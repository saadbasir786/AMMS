<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Support\AmmsRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAmmsRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true
            && $this->user()->hasScopedRole(AmmsRole::SYSTEM_ADMIN)
            && $this->user()->can('role.manage');
    }

    public function rules(): array
    {
        return [
            'permissions' => ['required', 'array', 'max:100'],
            'permissions.*' => ['required', 'string', 'distinct'],
        ];
    }
}
