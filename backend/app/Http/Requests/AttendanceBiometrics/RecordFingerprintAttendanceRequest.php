<?php

namespace App\Http\Requests\AttendanceBiometrics;

use Illuminate\Foundation\Http\FormRequest;

class RecordFingerprintAttendanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_event_uuid' => ['required', 'uuid'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'matched_template_uuid' => ['required', 'uuid'],
            'finger_position' => ['nullable', 'string', 'max:30'],
            'matcher_vendor' => ['nullable', 'string', 'max:120'],
            'matcher_algorithm_version' => ['nullable', 'string', 'max:120'],
            'match_score' => ['nullable', 'numeric'],
            'threshold_reference' => ['nullable', 'string', 'max:160'],
            'candidate_set_checksum' => ['required', 'string', 'size:64'],
            'device_captured_at' => ['nullable', 'date'],
            'fingerprint_image' => ['prohibited'],
            'raw_image' => ['prohibited'],
            'probe_template' => ['prohibited'],
            'candidate_template_bytes' => ['prohibited'],
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if ($validator->errors()->hasAny(['fingerprint_image', 'raw_image', 'probe_template', 'candidate_template_bytes'])) {
            abort(response()->json(['success' => false, 'code' => 'RAW_BIOMETRIC_IMAGE_NOT_ACCEPTED', 'message' => 'دادهٔ خام بایومتریک پذیرفته نیست.', 'errors' => []], 422));
        }
        parent::failedValidation($validator);
    }
}
