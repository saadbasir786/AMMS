<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true && $this->user()->can('employee.manage'); }
    public function rules(): array { return ['employee_number'=>['required','string','max:50','unique:employees,employee_number,'.$this->route('employee')?->id],'first_name'=>['required','string','max:100'],'last_name'=>['required','string','max:100'],'father_name'=>['nullable','string','max:100'],'organizational_unit_id'=>['required','integer','exists:organizational_units,id'],'position_id'=>['nullable','integer','exists:positions,id'],'email'=>['nullable','email','max:255'],'phone'=>['nullable','string','max:50'],'status'=>['sometimes',Rule::in(['ACTIVE','INACTIVE'])]]; }
    public function messages(): array { return ['required'=>'وارد کردن :attribute الزامی است.','unique'=>'این شمارهٔ کارمندی قبلاً ثبت شده است.','exists'=>'مقدار انتخاب‌شده معتبر نیست.']; }
    public function attributes(): array { return ['employee_number'=>'شمارهٔ کارمندی','first_name'=>'نام','last_name'=>'تخلص','organizational_unit_id'=>'واحد تشکیلاتی','position_id'=>'سمت']; }
}
