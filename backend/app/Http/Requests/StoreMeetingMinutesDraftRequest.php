<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingMinutesDraftRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:20000'],
            'general_notes' => ['nullable', 'string', 'max:20000'],
            'items' => ['required', 'array'],
            'items.*.agenda_title' => ['required', 'string', 'max:500'],
            'items.*.discussion_summary' => ['required', 'string', 'max:10000'],
            'decisions' => ['required', 'array'],
            'decisions.*.decision_text' => ['required', 'string', 'max:10000'],
            'decisions.*.responsible_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'decisions.*.responsible_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'decisions.*.target_date' => ['nullable', 'date'],
            'decisions.*.notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
