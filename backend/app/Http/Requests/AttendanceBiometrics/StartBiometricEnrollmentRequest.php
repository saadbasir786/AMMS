<?php

namespace App\Http\Requests\AttendanceBiometrics;

use Illuminate\Foundation\Http\FormRequest;

class StartBiometricEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true && $this->user()->hasPermissionTo('biometric.enroll', 'sanctum');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'terminal_id' => ['nullable', 'integer', 'exists:terminals,id'],
            'required_finger_count' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ];
    }
}
