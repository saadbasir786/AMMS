<?php

namespace App\Http\Requests\AttendanceBiometrics;

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

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if ($validator->errors()->has('reason')) {
            abort(response()->json(['success' => false, 'code' => 'MANUAL_ATTENDANCE_REASON_REQUIRED', 'message' => 'ثبت دستی حضور به دلیل نیاز دارد.', 'errors' => $validator->errors()], 422));
        }
        parent::failedValidation($validator);
    }
}
