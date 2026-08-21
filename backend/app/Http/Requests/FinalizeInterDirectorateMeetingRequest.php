<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeInterDirectorateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true && $this->user()->can('meeting.create');
    }

    public function rules(): array
    {
        return [
            'chairperson_employee_id' => ['required', 'integer', 'exists:employees,id'],
            'agenda' => ['nullable', 'string'],
            'target_coordinator_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'location' => ['prohibited'],
            'end_time' => ['prohibited'],
            'meeting_date' => ['prohibited'],
            'start_time' => ['prohibited'],
        ];
    }
}
