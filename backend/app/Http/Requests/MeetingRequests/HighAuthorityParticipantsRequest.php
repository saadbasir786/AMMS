<?php

namespace App\Http\Requests\MeetingRequests;

use Illuminate\Foundation\Http\FormRequest;

class HighAuthorityParticipantsRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true; }
    public function rules(): array { return ['employee_ids' => ['required', 'array', 'min:1'], 'employee_ids.*' => ['required', 'integer', 'distinct', 'exists:employees,id']]; }
}
