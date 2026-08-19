<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthorityScheduleProposalRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true && $this->user()->can('authority-schedule.propose'); }
    public function rules(): array
    {
        return [
            'meeting_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
