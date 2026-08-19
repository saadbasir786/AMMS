<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthorityScheduleAlternativeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true; }
    public function rules(): array
    {
        return [
            'meeting_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }
}
