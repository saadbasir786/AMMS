<?php

namespace App\Http\Requests\AttendanceBiometrics;

use Illuminate\Foundation\Http\FormRequest;

class PairTerminalRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'pairing_code' => ['required', 'string', 'regex:/^\d{4}$/D'],
        ];
    }
}
