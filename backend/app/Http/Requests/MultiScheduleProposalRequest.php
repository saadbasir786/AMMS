<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MultiScheduleProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    public function rules(): array
    {
        return [
            'proposed_by_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'meeting_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'location' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
