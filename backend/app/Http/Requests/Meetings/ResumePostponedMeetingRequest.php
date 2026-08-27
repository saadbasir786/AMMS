<?php

declare(strict_types=1);

namespace App\Http\Requests\Meetings;

use Illuminate\Foundation\Http\FormRequest;

class ResumePostponedMeetingRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'meeting_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:500'],
        ];
    }
}
