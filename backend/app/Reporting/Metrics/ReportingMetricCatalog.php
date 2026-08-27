<?php

declare(strict_types=1);

namespace App\Reporting\Metrics;

final class ReportingMetricCatalog
{
    public const DEFINITION_VERSION = 'AMMS_REPORTING_V1';

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $definitions = [
            'requests.total' => self::definition('مجموع درخواست‌ها', 'Count of eligible meeting_requests created in the selected period.', 'meeting_requests.created_at'),
            'requests.converted' => self::definition('درخواست‌های تبدیل‌شده', 'Requests created in the cohort that currently reached CONVERTED_TO_MEETING.', 'meeting_requests.created_at + current status'),
            'requests.cohort_conversion_rate' => self::definition('نرخ تبدیل گروهی', 'Converted requests in the created-date cohort divided by all eligible requests in the cohort.', 'meeting_requests.created_at'),
            'meetings.distinct_total' => self::definition('مجموع جلسات', 'COUNT(DISTINCT meetings.id) in the authorized scope.', 'meetings.created_at'),
            'meetings.status_distribution' => self::definition('توزیع وضعیت جلسات', 'Distinct Meetings grouped by their frozen current status.', 'current state as_of'),
            'action_items.by_status' => self::definition('اقدامات بر اساس وضعیت', 'Action Items grouped by exact frozen status.', 'current state as_of'),
            'followups.by_classification' => self::definition('پیگیری بر اساس طبقه‌بندی', 'Follow-up roots grouped by frozen classification.', 'classified_at when available'),
            'followups.by_status' => self::definition('پیگیری بر اساس وضعیت', 'Follow-up roots grouped by frozen status.', 'current state'),
            'exceptions.applied_by_type' => self::definition('استثناها بر اساس نوع', 'Applied meeting-exception cases grouped by case_type.', 'applied_at'),
            'meetings.conducted' => self::definition('جلسات برگزارشده', 'Meetings with valid execution history and status HELD or COMPLETED.', 'meeting_execution_sessions'),
            'meetings.upcoming' => self::definition('جلسات پیش‌رو', 'SCHEDULED Meetings whose current official schedule is future.', 'current schedule version'),
            'meetings.high_authority' => self::definition('جلسات مقام عالی', 'Meetings with an authoritative frozen authority context.', 'authority context / meeting creation'),
            'attendance.official_present' => self::definition('حضور رسمی ثبت‌شده', 'Distinct employees returned by OfficialAttendanceResolver.', 'meeting_attendance_records.recorded_at_server'),
            'attendance.invalidated' => self::definition('حضور باطل‌شده', 'Raw attendance records with a meeting-exception invalidation.', 'meeting_attendance_invalidations.invalidated_at'),
            'attendance.rate' => self::definition('نرخ حضور رسمی', 'Distinct official present employees divided by the bound execution participant denominator.', 'execution/minute participant version'),
            'decisions.finalized' => self::definition('تصمیم‌های نهایی‌شده', 'Decisions from FINALIZED Minutes and the final authoritative Minute Version.', 'meeting_minutes.finalized_at'),
            'followups.total' => self::definition('مجموع پیگیری‌ها', 'Follow-up roots attached to authoritative Decisions.', 'classified_at when available'),
            'action_items.total' => self::definition('مجموع اقدامات', 'Eligible Action Items in the authorized scope.', 'created_at'),
            'action_items.overdue' => self::definition('اقدامات سررسیدگذشته', 'Due date before organization-local today and status not terminal.', 'as_of local date'),
            'action_items.completion_rate' => self::definition('نرخ تکمیل اقدامات', 'COMPLETED divided by OPEN + IN_PROGRESS + BLOCKED + PENDING_VERIFICATION + COMPLETED.', 'current state as_of'),
            'exceptions.postponement_applied' => self::definition('تعویق‌های اعمال‌شده', 'Applied postponement exception cases.', 'meeting_exception_cases.applied_at'),
            'exceptions.cancellation_applied' => self::definition('لغوهای اعمال‌شده', 'Applied cancellation exception cases.', 'meeting_exception_cases.applied_at'),
            'exceptions.reopening_applied' => self::definition('بازگشایی‌های اعمال‌شده', 'Applied reopening exception cases.', 'meeting_exception_cases.applied_at'),
            'exceptions.resumption_applied' => self::definition('ازسرگیری‌های اعمال‌شده', 'Applied resumption exception cases, separate from rescheduling.', 'meeting_exception_cases.applied_at'),
            'reschedules.confirmed' => self::definition('زمان‌بندی‌های مجدد تأییدشده', 'Confirmed reschedule cases/proposals.', 'reschedule confirmation timestamp'),
            'participant_changes.confirmed' => self::definition('تغییرات اشتراک‌کنندگان تأییدشده', 'Cases reaching confirmed participant-version change.', 'participant-change confirmation timestamp'),
            'terminals.total' => self::definition('مجموع ترمینال‌ها', 'Count of organization terminals in technical scope.', 'current state'),
            'terminals.by_status' => self::definition('ترمینال‌ها بر اساس وضعیت', 'Count of terminals grouped by frozen status.', 'current state'),
            'terminal_sessions.active' => self::definition('نشست‌های فعال ترمینال', 'Count of active terminal sessions without serializing tokens.', 'current state'),
            'terminal_assignments.active' => self::definition('تخصیص‌های فعال ترمینال', 'Count of active terminal assignments.', 'current state'),
        ];
        $catalog = [];
        foreach ($definitions as $metricId => $definition) $catalog[$metricId] = array_merge($definition, ['metric_id' => $metricId]);
        return $catalog;
    }

    public static function get(string $metricId): array
    {
        return self::all()[$metricId] ?? throw new \InvalidArgumentException('UNKNOWN_REPORTING_METRIC');
    }

    private static function definition(string $label, string $formula, string $timestampBasis): array
    {
        return [
            'metric_id' => null,
            'label' => $label,
            'formula' => $formula,
            'timestamp_basis' => $timestampBasis,
            'scope_basis' => 'permission + AMMS scope + resource scope',
            'unit_attribution' => 'REQUESTING_UNIT unless explicitly declared otherwise',
            'definition_version' => self::DEFINITION_VERSION,
            'null_semantics' => 'percentage is null when denominator is zero',
        ];
    }
}
