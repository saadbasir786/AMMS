<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingExceptionDraftRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:5000'],
            'administrative_note' => ['nullable', 'string', 'max:5000'],
            'replacement_expected' => ['sometimes', 'boolean'],
            'proposed_resume_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
