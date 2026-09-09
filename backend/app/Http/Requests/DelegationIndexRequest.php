<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DelegationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', 'in:ACTIVE,REVOKED,EXPIRED'],
            'organizational_unit_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }
}
