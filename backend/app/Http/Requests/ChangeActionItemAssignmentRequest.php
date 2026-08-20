<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeActionItemAssignmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'responsible_unit_id' => ['nullable', 'integer', 'exists:organizational_units,id'],
            'assigned_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'reason' => ['required', 'string', 'max:5000'],
        ];
    }
}
