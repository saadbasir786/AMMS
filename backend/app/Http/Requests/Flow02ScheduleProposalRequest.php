<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Flow02ScheduleProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true && $this->user()->can('meeting-schedule.propose');
    }

    public function rules(): array
    {
        return [
            'proposed_by_unit_id' => ['required', 'integer', 'exists:organizational_units,id'],
            'meeting_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'location' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['required' => 'وارد کردن :attribute الزامی است.', 'after' => 'زمان ختم باید بعد از زمان آغاز باشد.'];
    }

    public function attributes(): array
    {
        return ['proposed_by_unit_id' => 'ریاست پیشنهاددهنده', 'meeting_date' => 'تاریخ جلسه', 'start_time' => 'زمان آغاز', 'end_time' => 'زمان ختم', 'location' => 'محل جلسه', 'comment' => 'یادداشت'];
    }
}
