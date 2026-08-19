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
            'location' => ['nullable', 'string', 'max:255'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'agenda' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
