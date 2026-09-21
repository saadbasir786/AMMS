<?php

namespace App\Http\Requests\MeetingRequests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleMeetingRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true; }

    public function rules(): array
    {
        return [
            'meeting_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'location' => ['nullable', 'string', 'max:255'],
            'venue_type' => ['nullable', 'in:INTERNAL_VENUE,EXTERNAL_LOCATION,ONLINE'],
            'venue_id' => ['nullable', 'integer', 'exists:meeting_venues,id'],
            'chairperson_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'exists:employees,id'],
        ];
    }
}
