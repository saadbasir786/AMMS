<?php

declare(strict_types=1);

namespace App\Http\Requests\Organization;

use App\Support\AmmsRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAmmsRoleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:64', 'regex:/^[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)*$/'],
            'label' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'نام نقش باید با حروف بزرگ و قالب SNAKE_CASE باشد.',
            'label.required' => 'عنوان نقش الزامی است.',
        ];
    }
}
