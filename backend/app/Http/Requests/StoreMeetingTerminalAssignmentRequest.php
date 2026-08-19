<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingTerminalAssignmentRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true && $this->user()->can('meeting-terminal.assign'); }

    public function rules(): array
    {
        return [
            'terminal_id' => ['required', 'integer', 'exists:terminals,id'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
        ];
    }
}
