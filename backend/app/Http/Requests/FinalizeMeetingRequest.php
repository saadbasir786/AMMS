<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    public function rules(): array
    {
        return [
            'agenda' => ['nullable','string','max:10000'],
        ];
    }

    public function attributes(): array
    {
        return ['agenda' => 'آجندا'];
    }
}
