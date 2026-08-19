<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MultiScheduleAcceptanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    public function rules(): array
    {
        return ['unit_id' => ['nullable', 'integer', 'exists:organizational_units,id']];
    }
}
