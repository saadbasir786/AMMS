<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInterDirectorateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true && $this->user()->can('meeting-request.create');
    }

    public function rules(): array
    {
        return [
            'requesting_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'target_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'subject' => ['required', 'string', 'max:255'],
            'purpose' => ['required', Rule::in(['DECISION','COORDINATION','CONSULTATION','REPORTING','APPROVAL','FOLLOW_UP','OTHER'])],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(['NORMAL','HIGH','URGENT'])],
            'preferred_date' => ['nullable', 'date'],
            'preferred_start_time' => ['nullable', 'date_format:H:i'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'proposed_location' => ['nullable', 'string', 'max:255'],
            'participant_ids' => ['sometimes', 'array'],
            'participant_ids.*' => ['integer', 'exists:employees,id'],
        ];
    }

    public function attributes(): array
    {
        return ['requesting_unit_id' => 'ریاست درخواست‌کننده', 'target_unit_id' => 'ریاست مقصد', 'subject' => 'موضوع جلسه', 'purpose' => 'هدف جلسه', 'priority' => 'اولویت', 'participant_ids' => 'اشتراک‌کنندگان'];
    }

    public function messages(): array
    {
        return ['required' => 'وارد کردن :attribute الزامی است.', 'exists' => 'مقدار انتخاب‌شده برای :attribute معتبر نیست.', 'participant_ids.*.exists' => 'یکی از اشتراک‌کنندگان انتخاب‌شده معتبر نیست.'];
    }
}
