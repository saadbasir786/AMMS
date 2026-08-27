<?php

declare(strict_types=1);

namespace App\Http\Requests\ActionItems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassifyMeetingDecisionRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['classification' => ['required', Rule::in(['ACTION_REQUIRED', 'NO_ACTION_REQUIRED'])], 'reason' => ['nullable', 'string', 'max:5000']];
    }
}
