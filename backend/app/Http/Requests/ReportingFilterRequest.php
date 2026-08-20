<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Reporting\Filters\ReportingFilter;
use Illuminate\Foundation\Http\FormRequest;

class ReportingFilterRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->isActive() === true; }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'flow_type' => ['nullable', 'string', 'max:64'],
            'meeting_status' => ['nullable', 'string', 'max:64'],
            'request_status' => ['nullable', 'string', 'max:64'],
            'requesting_unit_id' => ['nullable', 'integer', 'min:1'],
            'participating_unit_id' => ['nullable', 'integer', 'min:1'],
            'involved_unit_id' => ['nullable', 'integer', 'min:1'],
            'high_authority_only' => ['nullable', 'boolean'],
            'action_item_status' => ['nullable', 'in:OPEN,IN_PROGRESS,BLOCKED,PENDING_VERIFICATION,COMPLETED,CANCELLED'],
            'followup_status' => ['nullable', 'string', 'max:32'],
            'overdue' => ['nullable', 'boolean'],
            'exception_type' => ['nullable', 'string', 'max:32'],
            'attendance_state' => ['nullable', 'in:OFFICIAL,INVALIDATED,ALL'],
            'search' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'in:meeting_date,meeting_number,created_at,due_date,status,completed_at'],
            'direction' => ['nullable', 'in:asc,desc,ASC,DESC'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'attribution' => ['nullable', 'in:REQUESTING_UNIT,PARTICIPATING_UNIT,INVOLVED_UNIT'],
        ];
    }

    public function filters(): ReportingFilter { return ReportingFilter::fromRequest($this); }
}
