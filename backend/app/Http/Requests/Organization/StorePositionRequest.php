<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true
            && $this->user()->hasScopedRole('SYSTEM_ADMIN')
            && $this->user()->can('organization.manage');
    }

    public function rules(): array
    {
        return [
            'organizational_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'title' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'position_type' => [
                'nullable',
                Rule::in([
                    'DIRECTOR',
                    'DEPUTY_DIRECTOR',
                    'EXECUTIVE_MANAGEMENT',
                    'EMPLOYEE',
                    'HIGH_AUTHORITY',
                    'AUTHORITY_OFFICE',
                    'EXECUTIVE_SECRETARY',
                ]),
            ],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE'])],
        ];
    }
}
