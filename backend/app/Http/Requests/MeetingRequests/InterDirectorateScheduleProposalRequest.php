<?php

namespace App\Http\Requests\MeetingRequests;

use Illuminate\Foundation\Http\FormRequest;

class InterDirectorateScheduleProposalRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true && $this->user()->can('meeting-schedule.propose'); }

    public function rules(): array
    {
        return [
            'proposed_by_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'meeting_date' => ['required', 'date'], 'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'venue_type' => ['nullable', 'in:INTERNAL_VENUE,EXTERNAL_LOCATION,ONLINE'],
            'venue_id' => ['nullable', 'integer', 'exists:meeting_venues,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
