<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true && $this->user()->hasScopedRole('SYSTEM_ADMIN') && ($this->user()->can('user.manage') || $this->user()->can('role.manage'));
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)], 'password' => [$userId ? 'nullable' : 'required', 'string', 'min:12'], 'employee_id' => ['nullable', 'integer', 'exists:employees,id', Rule::unique('users', 'employee_id')->ignore($userId)], 'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE'])]];
    }

    public function messages(): array
    {
        return ['required' => 'وارد کردن :attribute الزامی است.', 'unique' => 'این ایمیل قبلاً استفاده شده است.', 'password.min' => 'رمز عبور باید حداقل ۱۲ کاراکتر داشته باشد.'];
    }

    public function attributes(): array
    {
        return ['name' => 'نام کاربر', 'email' => 'ایمیل', 'password' => 'رمز عبور', 'employee_id' => 'کارمند'];
    }
}
