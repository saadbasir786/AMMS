<?php

namespace App\Http\Requests\Organization;

use App\Support\SpecialCapability;
use App\Support\AmmsRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCapabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user?->isActive() === true
            && $user->hasScopedRole(AmmsRole::SYSTEM_ADMIN)
            && $user->can('role.manage');
    }

    public function rules(): array
    {
        return [
            'capability' => ['required', 'string', Rule::in(SpecialCapability::systemAdminAssignable())],
            'organizational_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
        ];
    }
}
