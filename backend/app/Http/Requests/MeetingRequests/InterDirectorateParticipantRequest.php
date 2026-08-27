<?php

namespace App\Http\Requests\MeetingRequests;

use Illuminate\Foundation\Http\FormRequest;

class InterDirectorateParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true && $this->user()->can('meeting-participant.nominate');
    }

    public function rules(): array
    {
        return ['nominated_by_unit_id' => ['required', 'integer', 'exists:organizational_units,id'], 'employee_id' => ['required', 'integer', 'exists:employees,id']];
    }
}
