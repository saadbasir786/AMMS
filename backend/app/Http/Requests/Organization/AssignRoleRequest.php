<?php

namespace App\Http\Requests\Organization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true && $this->user()->hasScopedRole('SYSTEM_ADMIN') && $this->user()->can('role.manage'); }
    public function rules(): array { return ['role'=>['required',Rule::in(['SYSTEM_ADMIN','EMPLOYEE','DIRECTOR','DEPUTY_DIRECTOR','EXECUTIVE_MANAGEMENT','AUTHORITY_OFFICE','EXECUTIVE_SECRETARY','HIGH_AUTHORITY'])],'organizational_unit_id'=>['nullable','integer','exists:organizational_units,id']]; }
    public function messages(): array { return ['required'=>'وارد کردن نقش الزامی است.','exists'=>'واحد تشکیلاتی معتبر نیست.']; }
}
