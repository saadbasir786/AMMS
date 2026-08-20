# AMMS FLOW-14 Metric Catalog

> **Definition version:** `AMMS_REPORTING_V1`
>
> FLOW-14 metrics are read-only observations of frozen transactional data. The Laravel reporting layer owns every formula; React receives values and series without recomputing business semantics.

## Semantic Conventions

All date filters are interpreted by the backend as a half-open range `[from 00:00, to + 1 day 00:00)` in the configured application timezone. Every response returns `from`, `to`, `timezone`, `as_of`, and `definition_version`. A zero is a measured zero. A `null` percentage means that its denominator is zero or that the authoritative denominator is unavailable; the frontend must label this as “No data” rather than rendering `0%`.

Organization-wide totals use `COUNT(DISTINCT meetings.id)` or the corresponding distinct source identity. Unit-grouped results declare an attribution mode because one cross-unit Meeting may appear in more than one group.

## Metric Definitions

| Metric ID | Persian/Dari label | Formula and authoritative source | Timestamp basis | Scope and attribution | Exclusions / null behavior |
|---|---|---|---|---|---|
| `requests.total` | مجموع درخواست‌ها | Count of eligible `meeting_requests` | `meeting_requests.created_at` | Requesting/involved scope | None beyond authorization scope |
| `requests.by_flow_type` | درخواست‌ها بر اساس جریان | Group eligible requests by frozen `flow_type` | `created_at` | Requesting-unit attribution | No invented aggregate status |
| `requests.converted` | درخواست‌های تبدیل‌شده | Requests with `status=CONVERTED_TO_MEETING` | Created-date cohort, current outcome | Requesting-unit attribution | Current status is not a processing-speed metric |
| `requests.rejected` | درخواست‌های ردشده | Requests with frozen rejected statuses | `created_at` | Requesting-unit attribution | Status values remain distinct |
| `requests.returned` | درخواست‌های برگشتی | Requests with frozen returned statuses | `created_at` | Requesting-unit attribution | Status values remain distinct |
| `requests.pending` | درخواست‌های در انتظار | Requests not in terminal request status | Current state | Requesting-unit attribution | No invented `PENDING` status |
| `requests.cohort_conversion_rate` | نرخ تبدیل گروهی | Cohort-created requests currently converted divided by all eligible requests created in period | `created_at` cohort | Requesting-unit attribution | `null` when denominator is zero |
| `meetings.distinct_total` | مجموع جلسات | `COUNT(DISTINCT meetings.id)` | `meetings.created_at` for period reports | Organization distinct total | Never unit-summed for organization total |
| `meetings.status_distribution` | توزیع وضعیت جلسات | Distinct Meeting counts grouped by frozen status | Current state `as_of` | Declared attribution mode | No status inferred in React |
| `meetings.conducted` | جلسات برگزارشده | Meetings with frozen execution history and status `HELD` or `COMPLETED` | Execution start/end timestamps | Meeting distinct total | Excludes `SCHEDULED`, `POSTPONED`, `CANCELLED` |
| `meetings.upcoming` | جلسات پیش‌رو | `status=SCHEDULED` and current official schedule is future | Current schedule version date/time | Meeting distinct total | Excludes historical schedules, postponed and cancelled Meetings |
| `meetings.completed` | جلسات تکمیل‌شده | Meetings with `status=COMPLETED` | Meeting completion/execution timestamp when available | Meeting distinct total | Current status only for current-state card |
| `meetings.cancelled` | جلسات لغوشده | Meetings with `status=CANCELLED` for current distribution, or APPLIED cancellation cases for event reports | `meeting_exception_cases.applied_at` for events | Meeting distinct total | Event metric never reconstructed from current status |
| `meetings.postponed` | جلسات به‌تعویق‌افتاده | Meetings with `status=POSTPONED` for current distribution, or APPLIED postponement cases for event reports | `applied_at` for events | Meeting distinct total | FLOW-13 event history remains separate |
| `meetings.high_authority` | جلسات مقام عالی | Meetings with an authoritative frozen `authorityContext` | Meeting creation or event timestamp as applicable | Organization or authorized authority scope | Never inferred from names, titles, or role strings |
| `attendance.official_present` | حضور رسمی ثبت‌شده | Count of distinct employees in `OfficialAttendanceResolver` results | `recorded_at_server` | Meeting/unit scope; employee detail requires detail permission | FLOW-13-invalidated rows excluded; duplicates excluded |
| `attendance.invalidated` | حضور باطل‌شده | Count of raw attendance records having a FLOW-13 invalidation | `invalidated_at` | Authorized attendance/audit scope | Raw rows remain immutable |
| `attendance.rate` | نرخ حضور رسمی | Distinct official present employees divided by distinct members of the current participant version for meetings with recorded attendance | `recorded_at_server` and current snapshot | Meeting/unit scope | `null` when denominator is zero |
| `attendance.expected_participants` | اشتراک‌کنندگان مورد انتظار | Distinct members of the current participant version for the scoped Meetings that have at least one attendance record | Current participant-version identity plus attendance-record existence | Meeting scope | Scheduled Meetings without recorded attendance do not inflate the denominator |
| `decisions.finalized` | تصمیم‌های نهایی‌شده | Decisions from `FINALIZED` Minutes and the final authoritative Minute Version only | `meeting_minutes.finalized_at` | Meeting/unit scope | Superseded Minute Versions excluded |
| `decisions.with_target_date` | تصمیم‌های دارای تاریخ هدف | Finalized Decisions with documentary `target_date` | Finalized timestamp | Responsible-unit attribution | No operational due date inference |
| `decisions.without_target_date` | تصمیم‌های بدون تاریخ هدف | Finalized Decisions without documentary `target_date` | Finalized timestamp | Responsible-unit attribution | No mutation of source Decision |
| `followups.total` | مجموع پیگیری‌ها | FLOW-12 follow-up roots attached to authoritative Decisions | `classified_at` when available | Meeting/decision scope | Follow-up classification comes from frozen root |
| `followups.by_classification` | پیگیری بر اساس طبقه‌بندی | Group follow-ups by frozen `classification` | `classified_at` | Meeting/decision scope | Never inferred from Action Item presence |
| `followups.by_status` | پیگیری بر اساس وضعیت | Group follow-ups by frozen `status` | Current state or status timestamp | Meeting/decision scope | No invented status |
| `action_items.total` | مجموع اقدامات | Count of eligible FLOW-12 Action Items | Created timestamp | Authorized unit/employee scope | Evidence bytes and private paths excluded |
| `action_items.by_status` | اقدامات بر اساس وضعیت | Group by exact frozen statuses: `OPEN`, `IN_PROGRESS`, `BLOCKED`, `PENDING_VERIFICATION`, `COMPLETED`, `CANCELLED` | Current state | Unit/employee scope | `OVERDUE` is never persisted |
| `action_items.overdue` | اقدامات سررسیدگذشته | `due_date < organization-local today` and status not terminal | Current state at `as_of` | Unit/employee scope | `COMPLETED` and `CANCELLED` excluded |
| `action_items.completion_rate` | نرخ تکمیل اقدامات | `COMPLETED / (OPEN + IN_PROGRESS + BLOCKED + PENDING_VERIFICATION + COMPLETED)` | Current state at `as_of` | Unit/employee scope | Cancelled excluded; `null` denominator zero |
| `action_items.on_time_completed` | تکمیل‌شده در موعد | Completed Action Items where `completed_at` is on or before approved operational due date | `completed_at` | Unit/employee scope | Does not use documentary Decision target date as operational deadline |
| `exceptions.postponement_applied` | تعویق‌های اعمال‌شده | APPLIED FLOW-13 `POSTPONEMENT` cases | `meeting_exception_cases.applied_at` | Authorized Meeting scope | Not reconstructed from current status |
| `exceptions.cancellation_applied` | لغوهای اعمال‌شده | APPLIED FLOW-13 `CANCELLATION` cases | `applied_at` | Authorized Meeting scope | Not reconstructed from current status |
| `exceptions.reopening_applied` | بازگشایی‌های اعمال‌شده | APPLIED FLOW-13 `REOPENING` cases | `applied_at` | Authorized Meeting scope | Not reconstructed from current status |
| `exceptions.resumption_applied` | ازسرگیری‌های اعمال‌شده | APPLIED FLOW-13 `RESUMPTION` cases | `applied_at` | Authorized Meeting scope | Kept separate from FLOW-07 rescheduling |
| `reschedules.confirmed` | زمان‌بندی‌های مجدد تأییدشده | FLOW-07 cases/proposals confirmed through authoritative FLOW-07 state | Confirmation timestamp | Authorized Meeting scope | FLOW-13 RESUMPTION excluded |
| `reschedules.schedule_version_average` | میانگین نسخه‌های زمان‌بندی | Average count of immutable schedule versions for eligible Meetings | Current snapshot | Meeting scope | Version creation is not historical mutation |
| `participant_changes.confirmed` | تغییرات اشتراک‌کنندگان تأییدشده | FLOW-08 cases reaching confirmed participant-version change | Confirmation timestamp | Authorized Meeting scope | Uses FLOW-08 history, not arbitrary live diffs |
| `participant_changes.by_operation` | تغییرات بر اساس عملیات | Group FLOW-08 items by `ADD`, `REMOVE`, `REPLACE` | Case confirmation timestamp | Authorized Meeting scope | Only authoritative case items |
| `terminals.total` | مجموع ترمینال‌ها | Count of organization terminals | Current state | `TECHNICAL_INFRASTRUCTURE` only | No credentials or biometric fields |
| `terminals.by_status` | ترمینال‌ها بر اساس وضعیت | Group terminals by frozen terminal status | Current state | Technical scope | Secret, hash, token and biometric fields excluded |
| `terminal_sessions.active` | نشست‌های فعال ترمینال | Count of active terminal sessions | Current state | Technical scope | Session token never serialized |

## Attribution Modes

`REQUESTING_UNIT` attributes a Meeting to its requester/organizer. `PARTICIPATING_UNIT` attributes it to each formal participating unit. `INVOLVED_UNIT` includes requesting and participating formal units. Organization-wide distinct totals always count one Meeting once; grouped unit totals disclose that a cross-unit Meeting may appear in multiple groups.

## Unsupported Semantics

FLOW-14 does not fabricate missing historical timestamps, introduce qualitative performance statuses, rank employees or Directorates, infer official Decisions from superseded Minute Versions, infer official attendance from raw rows, or treat FLOW-13 RESUMPTION as FLOW-07 rescheduling.
