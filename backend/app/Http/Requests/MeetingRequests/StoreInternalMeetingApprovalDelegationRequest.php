<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInternalMeetingApprovalDelegationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true;
    }

    public function rules(): array
    {
        return [
            'delegate_user_id' => ['required', 'integer', 'exists:users,id'],
            'organizational_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'expires_at' => ['required', 'date', 'after:today'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }
}
