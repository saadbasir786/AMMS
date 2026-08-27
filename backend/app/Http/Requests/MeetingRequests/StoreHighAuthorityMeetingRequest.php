<?php

namespace App\Http\Requests\MeetingRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHighAuthorityMeetingRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true && $this->user()->can('meeting-request.create'); }

    public function rules(): array
    {
        return [
            'requesting_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'subject' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::in(['DECISION','COORDINATION','CONSULTATION','REPORTING','APPROVAL','FOLLOW_UP','OTHER'])],
            'description' => ['nullable', 'string'],
            'meeting_justification' => ['required', 'string', 'max:5000'],
            'requested_outcome' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['NORMAL','HIGH','URGENT'])],
            'preferred_date' => ['nullable', 'date'],
            'preferred_start_time' => ['nullable', 'date_format:H:i'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'proposed_location' => ['nullable', 'string', 'max:255'],
            'participant_ids' => ['sometimes', 'array'],
            'participant_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
        ];
    }
}
