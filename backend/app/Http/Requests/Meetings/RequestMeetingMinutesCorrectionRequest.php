<?php

declare(strict_types=1);

namespace App\Http\Requests\Meetings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestMeetingMinutesCorrectionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reviewer_type' => ['required', Rule::in(['UNIT', 'HIGH_AUTHORITY'])],
            'organizational_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'reason' => ['required', 'string', 'min:2', 'max:10000'],
        ];
    }
}
