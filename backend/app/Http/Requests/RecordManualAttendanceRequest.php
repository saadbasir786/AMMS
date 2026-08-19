<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordManualAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true && $this->user()->can('attendance.override');
    }

    public function rules(): array
    {
        return ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'reason' => ['required', 'string', 'min:3', 'max:1000']];
    }
}
