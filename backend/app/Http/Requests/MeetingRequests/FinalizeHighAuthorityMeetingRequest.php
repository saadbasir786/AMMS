<?php

namespace App\Http\Requests\MeetingRequests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeHighAuthorityMeetingRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true && $this->user()->can('authority-meeting.finalize'); }
    public function rules(): array { return ['agenda' => ['nullable', 'string', 'max:5000']]; }
}
