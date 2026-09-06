<?php

namespace App\Http\Requests\Organization;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true && $this->user()->hasScopedRole('SYSTEM_ADMIN') && $this->user()->can('employee.manage');
    }

    public function rules(): array
    {
        return ['employee_number' => ['required', 'string', 'max:50', 'unique:employees,employee_number,'.$this->route('employee')?->id], 'first_name' => ['required', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'], 'father_name' => ['nullable', 'string', 'max:100'], 'organizational_unit_id' => ['required', 'integer', 'exists:organizational_units,id'], 'position_id' => ['nullable', 'integer', 'exists:positions,id'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:50'], 'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE'])]];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $positionId = $this->integer('position_id');
            $unitId = $this->integer('organizational_unit_id');
            if ($positionId && $unitId && Position::query()->whereKey($positionId)->where('organizational_unit_id', $unitId)->where('status', 'ACTIVE')->doesntExist()) {
                $validator->errors()->add('position_id', 'سمت انتخاب‌شده باید فعال و مربوط به واحد سازمانی کارمند باشد.');
            }
        }];
    }

    public function messages(): array
    {
        return ['required' => 'وارد کردن :attribute الزامی است.', 'unique' => 'این شمارهٔ کارمندی قبلاً ثبت شده است.', 'exists' => 'مقدار انتخاب‌شده معتبر نیست.'];
    }

    public function attributes(): array
    {
        return ['employee_number' => 'شمارهٔ کارمندی', 'first_name' => 'نام', 'last_name' => 'تخلص', 'organizational_unit_id' => 'واحد تشکیلاتی', 'position_id' => 'سمت'];
    }
}
