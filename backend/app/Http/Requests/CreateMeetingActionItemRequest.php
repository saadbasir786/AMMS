<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMeetingActionItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'responsible_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'assigned_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
