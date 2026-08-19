<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTerminalRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true && $this->user()->can('terminal.manage'); }

    public function rules(): array
    {
        $terminal = $this->route('terminal');
        return [
            'code' => ['sometimes', 'string', 'max:80', Rule::unique('terminals', 'code')->where(fn ($q) => $q->where('organization_id', $terminal?->organization_id))->ignore($terminal?->id)],
            'name' => ['sometimes', 'string', 'max:180'],
            'serial_number' => ['nullable', 'string', 'max:180'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'platform' => ['nullable', 'string', 'max:80'],
            'os_version' => ['nullable', 'string', 'max:80'],
            'app_version' => ['nullable', 'string', 'max:80'],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'INACTIVE', 'MAINTENANCE'])],
        ];
    }
}
