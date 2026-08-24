<?php

namespace App\Http\Requests;

use App\Support\SpecialCapability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCapabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user?->isActive() === true
            && $user->hasScopedRole('SYSTEM_ADMIN')
            && $user->can('role.manage');
    }

    public function rules(): array
    {
        return [
            'capability' => ['required', 'string', Rule::in(SpecialCapability::all())],
            'organizational_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
        ];
    }
}
