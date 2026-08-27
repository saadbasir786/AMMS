<?php

declare(strict_types=1);

namespace App\Http\Requests\Reporting;

use App\Reporting\Filters\ReportingFilter;
use Illuminate\Foundation\Http\FormRequest;

class ReportExportRequest extends ReportingFilterRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'report_type' => ['required', 'in:MEETING_REGISTER,MEETING_REQUEST_REGISTER,ATTENDANCE_REGISTER,DECISION_REGISTER,ACTION_ITEM_REGISTER,EXCEPTION_LIFECYCLE_REGISTER,DIRECTORATE_SUMMARY,HIGH_AUTHORITY_MEETING_REGISTER,RESCHEDULE_REGISTER,PARTICIPANT_CHANGE_REGISTER'],
            'format' => ['required', 'in:PDF,XLSX'],
        ]);
    }

    public function reportType(): string { return (string) $this->input('report_type'); }
    public function exportFormat(): string { return strtoupper((string) $this->input('format')); }
}
