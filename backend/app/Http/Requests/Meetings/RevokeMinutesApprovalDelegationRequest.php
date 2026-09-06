<?php

declare(strict_types=1);

namespace App\Http\Requests\Meetings;

use Illuminate\Foundation\Http\FormRequest;

class RevokeMinutesApprovalDelegationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:2000']];
    }
}
