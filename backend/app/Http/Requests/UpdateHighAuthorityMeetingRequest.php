<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHighAuthorityMeetingRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true && $this->user()->can('meeting-request.update'); }

    public function rules(): array
    {
        return [
            'requesting_unit_id' => ['sometimes', 'integer', 'exists:organizational_units,id'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'purpose' => ['sometimes', Rule::in(['DECISION','COORDINATION','CONSULTATION','REPORTING','APPROVAL','FOLLOW_UP','OTHER'])],
            'description' => ['sometimes', 'nullable', 'string'],
            'meeting_justification' => ['sometimes', 'string', 'max:5000'],
            'requested_outcome' => ['sometimes', 'string', 'max:5000'],
            'priority' => ['sometimes', Rule::in(['NORMAL','HIGH','URGENT'])],
            'preferred_date' => ['sometimes', 'nullable', 'date'],
            'preferred_start_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'estimated_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:15', 'max:480'],
            'proposed_location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'participant_ids' => ['sometimes', 'array'],
            'participant_ids.*' => ['integer', 'distinct', 'exists:employees,id'],
        ];
    }
}
