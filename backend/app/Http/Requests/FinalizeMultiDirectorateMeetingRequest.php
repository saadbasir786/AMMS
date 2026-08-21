<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeMultiDirectorateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    public function rules(): array
    {
        return [
            'chairperson_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'location' => ['prohibited'],
            'end_time' => ['prohibited'],
            'meeting_date' => ['prohibited'],
            'start_time' => ['prohibited'],
        ];
    }
}
