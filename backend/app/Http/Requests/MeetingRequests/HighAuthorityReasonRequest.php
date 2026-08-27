<?php

namespace App\Http\Requests\MeetingRequests;

use Illuminate\Foundation\Http\FormRequest;

class HighAuthorityReasonRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true; }
    public function rules(): array { return ['reason' => ['required', 'string', 'min:3', 'max:5000']]; }
}
