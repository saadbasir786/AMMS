<?php

namespace App\Http\Requests\Organization;

use App\Support\AmmsRole;
use Illuminate\Foundation\Http\FormRequest;

class AssignRoleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true && $this->user()->hasScopedRole(AmmsRole::SYSTEM_ADMIN) && $this->user()->can('role.manage'); }
    public function rules(): array { return ['role'=>['required','string','max:64','regex:/^[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)*$/'],'organizational_unit_id'=>['nullable','integer','exists:organizational_units,id']]; }
    public function messages(): array { return ['required'=>'وارد کردن نقش الزامی است.','exists'=>'واحد تشکیلاتی معتبر نیست.']; }
}
