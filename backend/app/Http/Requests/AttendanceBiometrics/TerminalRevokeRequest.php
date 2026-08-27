<?php

namespace App\Http\Requests\AttendanceBiometrics;

use Illuminate\Foundation\Http\FormRequest;

class TerminalRevokeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true; }
    public function rules(): array { return ['reason' => ['required', 'string', 'min:3', 'max:1000']]; }
}
