<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MultiParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    public function rules(): array
    {
        return [
            'organizational_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
        ];
    }
}
