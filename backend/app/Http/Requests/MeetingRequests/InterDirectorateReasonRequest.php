<?php

namespace App\Http\Requests\MeetingRequests;

use Illuminate\Foundation\Http\FormRequest;

class InterDirectorateReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:2000']];
    }

    public function attributes(): array
    {
        return ['reason' => 'دلیل'];
    }

    public function messages(): array
    {
        return ['required' => 'وارد کردن :attribute الزامی است.', 'min' => 'دلیل باید حداقل سه حرف داشته باشد.'];
    }
}
