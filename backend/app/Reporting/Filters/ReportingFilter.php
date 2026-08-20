<?php

declare(strict_types=1);

namespace App\Reporting\Filters;

use App\Reporting\Support\ReportingDateRange;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ReportingFilter
{
    public const REPORT_TYPES = [
        'MEETING_REGISTER', 'MEETING_REQUEST_REGISTER', 'ATTENDANCE_REGISTER',
        'DECISION_REGISTER', 'ACTION_ITEM_REGISTER', 'EXCEPTION_LIFECYCLE_REGISTER',
        'DIRECTORATE_SUMMARY', 'HIGH_AUTHORITY_MEETING_REGISTER', 'RESCHEDULE_REGISTER',
        'PARTICIPANT_CHANGE_REGISTER',
    ];

    public const ATTRIBUTIONS = ['REQUESTING_UNIT', 'PARTICIPATING_UNIT', 'INVOLVED_UNIT'];
    public const SORTS = ['meeting_date', 'meeting_number', 'created_at', 'due_date', 'status', 'completed_at'];
    public const ACTION_STATUSES = ['OPEN', 'IN_PROGRESS', 'BLOCKED', 'PENDING_VERIFICATION', 'COMPLETED', 'CANCELLED'];

    public function __construct(
        public readonly ReportingDateRange $dateRange,
        public readonly ?string $flowType,
        public readonly ?string $meetingStatus,
        public readonly ?string $requestStatus,
        public readonly ?int $requestingUnitId,
        public readonly ?int $participatingUnitId,
        public readonly ?int $involvedUnitId,
        public readonly ?bool $highAuthorityOnly,
        public readonly ?string $actionItemStatus,
        public readonly ?string $followupStatus,
        public readonly ?bool $overdue,
        public readonly ?string $exceptionType,
        public readonly ?string $attendanceState,
        public readonly ?string $search,
        public readonly string $sort,
        public readonly string $direction,
        public readonly int $perPage,
        public readonly string $attribution,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $sort = (string) ($request->input('sort', 'created_at'));
        $direction = strtolower((string) ($request->input('direction', 'desc')));
        if (!in_array($sort, self::SORTS, true) || !in_array($direction, ['asc', 'desc'], true)) {
            throw ValidationException::withMessages(['sort' => 'INVALID_REPORTING_SORT']);
        }
        $attribution = (string) ($request->input('attribution', 'INVOLVED_UNIT'));
        if (!in_array($attribution, self::ATTRIBUTIONS, true)) {
            throw ValidationException::withMessages(['attribution' => 'INVALID_REPORTING_ATTRIBUTION']);
        }
        $search = $request->input('search');
        if ($search !== null && mb_strlen((string) $search) > 120) {
            throw ValidationException::withMessages(['search' => 'REPORTING_SEARCH_TOO_LONG']);
        }
        $perPage = min(100, max(1, (int) $request->input('per_page', 25)));
        $bool = static fn ($value): ?bool => $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return new self(
            ReportingDateRange::fromInput($request->input('from'), $request->input('to'), $request->input('timezone')),
            $request->filled('flow_type') ? (string) $request->input('flow_type') : null,
            $request->filled('meeting_status') ? (string) $request->input('meeting_status') : null,
            $request->filled('request_status') ? (string) $request->input('request_status') : null,
            $request->filled('requesting_unit_id') ? (int) $request->input('requesting_unit_id') : null,
            $request->filled('participating_unit_id') ? (int) $request->input('participating_unit_id') : null,
            $request->filled('involved_unit_id') ? (int) $request->input('involved_unit_id') : null,
            $bool($request->input('high_authority_only')),
            $request->filled('action_item_status') ? (string) $request->input('action_item_status') : null,
            $request->filled('followup_status') ? (string) $request->input('followup_status') : null,
            $bool($request->input('overdue')),
            $request->filled('exception_type') ? (string) $request->input('exception_type') : null,
            $request->filled('attendance_state') ? (string) $request->input('attendance_state') : null,
            $search !== null ? trim((string) $search) : null,
            $sort,
            $direction,
            $perPage,
            $attribution,
        );
    }

    public function unitId(): ?int
    {
        return $this->involvedUnitId ?? $this->participatingUnitId ?? $this->requestingUnitId;
    }

    public function toArray(): array
    {
        return [
            'from' => $this->dateRange->from->toDateString(),
            'to' => $this->dateRange->toExclusive->subDay()->toDateString(),
            'timezone' => $this->dateRange->timezone,
            'flow_type' => $this->flowType,
            'meeting_status' => $this->meetingStatus,
            'request_status' => $this->requestStatus,
            'requesting_unit_id' => $this->requestingUnitId,
            'participating_unit_id' => $this->participatingUnitId,
            'involved_unit_id' => $this->involvedUnitId,
            'high_authority_only' => $this->highAuthorityOnly,
            'action_item_status' => $this->actionItemStatus,
            'followup_status' => $this->followupStatus,
            'overdue' => $this->overdue,
            'exception_type' => $this->exceptionType,
            'attendance_state' => $this->attendanceState,
            'search' => $this->search,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'per_page' => $this->perPage,
            'attribution' => $this->attribution,
        ];
    }
}
