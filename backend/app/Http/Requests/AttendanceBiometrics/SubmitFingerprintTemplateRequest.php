<?php

namespace App\Http\Requests\AttendanceBiometrics;

use Illuminate\Foundation\Http\FormRequest;

class SubmitFingerprintTemplateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'finger_position' => ['required', 'string', 'max:30'],
            'template_vendor' => ['required', 'string', 'max:120'],
            'template_format' => ['required', 'string', 'max:120'],
            'algorithm_version' => ['required', 'string', 'max:120'],
            // Fingerprint templates are compact feature data, not raw images. Keep a generous
            // upper bound to reject accidental/hostile multi-megabyte payloads at the API edge.
            'template_data' => ['required', 'string', 'max:262144'],
            'quality_score' => ['nullable', 'numeric'],
            'quality_accepted' => ['sometimes', 'boolean'],
            'replace_active' => ['sometimes', 'boolean'],
            'fingerprint_image' => ['prohibited'],
            'raw_image' => ['prohibited'],
            'probe_image' => ['prohibited'],
            'sensor_frame' => ['prohibited'],
            'screenshot' => ['prohibited'],
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        if ($validator->errors()->hasAny(['fingerprint_image', 'raw_image', 'probe_image', 'sensor_frame', 'screenshot'])) {
            abort(response()->json(['success' => false, 'code' => 'RAW_BIOMETRIC_IMAGE_NOT_ACCEPTED', 'message' => 'دادهٔ خام بایومتریک پذیرفته نیست.', 'errors' => []], 422));
        }
        parent::failedValidation($validator);
    }
}
