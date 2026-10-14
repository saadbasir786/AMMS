<?php

namespace App\Http\Requests\AttendanceBiometrics;

class SubmitMeetingParticipantFingerprintTemplateRequest extends SubmitFingerprintTemplateRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'replace_active' => ['prohibited'],
        ]);
    }
}
