<?php

declare(strict_types=1);

namespace App\Reporting\Queries;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingAttendanceRecord;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\MeetingRequest;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingParticipantChangeCase;
use App\Reporting\Filters\ReportingFilter;
use App\Reporting\Metrics\ReportingMetricCatalog;
use App\Reporting\Scopes\ReportingScopeResolver;
use App\Support\OfficialAttendanceResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class ReportingQueryService
{
    public function __construct(private readonly OfficialAttendanceResolver $attendanceResolver) {}

    public function dashboard(ReportingScopeResolver $scope, ReportingFilter $filters): array
    {
        $meetings = $this->meetingBase($scope, $filters);
        $meetingIds = (clone $meetings)->select('meetings.id');
        $requests = $this->requestBase($scope, $filters);
        $actions = $this->actionBase($scope, $filters);
        $decisions = $this->decisionBase($scope, $filters);
        $followups = $this->followupBase($scope, $filters);
        $exceptions = $this->exceptionBase($scope, $filters);

        $statusCounts = (clone $meetings)->select('meetings.status', DB::raw('COUNT(DISTINCT meetings.id) AS total'))->groupBy('meetings.status')->pluck('total', 'status')->map(fn ($v) => (int) $v)->all();
        $requestTotal = (clone $requests)->count();
        $convertedRequests = (clone $requests)->where('meeting_requests.status', 'CONVERTED_TO_MEETING')->count();
        $conducted = (clone $meetings)->whereIn('meetings.status', ['HELD', 'COMPLETED'])->whereHas('executionSession', fn (Builder $q) => $q->whereNotNull('ended_at'))->distinct('meetings.id')->count('meetings.id');
        $upcoming = (clone $meetings)->where('meetings.status', 'SCHEDULED')->whereHas('currentScheduleVersion', function (Builder $q) use ($filters): void {
            $q->whereDate('meeting_date', '>=', $filters->dateRange->from->toDateString())->whereDate('meeting_date', '<', $filters->dateRange->toExclusive->toDateString());
        })->distinct('meetings.id')->count('meetings.id');
        $highAuthority = (clone $meetings)->whereHas('authorityContext')->distinct('meetings.id')->count('meetings.id');

        $officialAttendance = MeetingAttendanceRecord::query()->whereIn('meeting_attendance_records.meeting_id', $meetingIds)->whereDoesntHave('invalidation');
        $officialPresent = (clone $officialAttendance)->distinct('employee_id')->count('employee_id');
        $invalidatedAttendance = MeetingAttendanceRecord::query()->whereIn('meeting_id', $meetingIds)->whereHas('invalidation')->count();
        $expectedParticipants = DB::table('meeting_participant_version_members as members')
            ->join('meetings', 'meetings.current_participant_version_id', '=', 'members.participant_version_id')
            ->whereIn('meetings.id', $meetingIds)
            ->whereExists(fn ($sub) => $sub->selectRaw('1')->from('meeting_attendance_records as attendance')->whereColumn('attendance.meeting_id', 'meetings.id'))
            ->distinct('members.employee_id')->count('members.employee_id');
        $attendanceRate = $expectedParticipants > 0 ? round($officialPresent / $expectedParticipants * 100, 2) : null;

        $finalizedDecisions = (clone $decisions)->count('meeting_minute_version_decisions.id');
        $followupTotal = (clone $followups)->count('meeting_decision_followups.id');
        $actionStatusCounts = (clone $actions)->select('meeting_action_items.status', DB::raw('COUNT(*) AS total'))->groupBy('meeting_action_items.status')->pluck('total', 'status')->map(fn ($v) => (int) $v)->all();
        $overdue = (clone $actions)->whereDate('meeting_action_items.due_date', '<', CarbonImmutable::now($filters->dateRange->timezone)->toDateString())->whereNotIn('meeting_action_items.status', [MeetingActionItem::COMPLETED, MeetingActionItem::CANCELLED])->count();
        $completionDenominator = array_sum(array_intersect_key($actionStatusCounts, array_flip([
            MeetingActionItem::OPEN, MeetingActionItem::IN_PROGRESS, MeetingActionItem::BLOCKED, MeetingActionItem::PENDING_VERIFICATION, MeetingActionItem::COMPLETED,
        ])));
        $completionRate = $completionDenominator > 0 ? round((($actionStatusCounts[MeetingActionItem::COMPLETED] ?? 0) / $completionDenominator) * 100, 2) : null;

        $exceptionCounts = (clone $exceptions)->select('meeting_exception_cases.case_type', DB::raw('COUNT(*) AS total'))->where('meeting_exception_cases.status', 'APPLIED')->groupBy('meeting_exception_cases.case_type')->pluck('total', 'case_type')->map(fn ($v) => (int) $v)->all();
        $reschedules = $this->rescheduleCount($scope, $filters);
        $participantChanges = $this->participantChangeCount($scope, $filters);

        return [
            'scope' => $this->scopeDescriptor($scope, $filters),
            'period' => $filters->dateRange->toArray(),
            'as_of' => now($filters->dateRange->timezone)->toIso8601String(),
            'definition_version' => ReportingMetricCatalog::DEFINITION_VERSION,
            'attribution_mode' => $filters->attribution,
            'cards' => [
                $this->card('requests.total', $requestTotal),
                $this->card('requests.converted', $convertedRequests),
                $this->card('requests.cohort_conversion_rate', $requestTotal > 0 ? round($convertedRequests / $requestTotal * 100, 2) : null),
                $this->card('meetings.distinct_total', (clone $meetings)->distinct('meetings.id')->count('meetings.id')),
                $this->card('meetings.conducted', $conducted),
                $this->card('meetings.upcoming', $upcoming),
                $this->card('meetings.high_authority', $highAuthority),
                $this->card('attendance.official_present', $officialPresent),
                $this->card('attendance.rate', $attendanceRate, '%'),
                $this->card('decisions.finalized', $finalizedDecisions),
                $this->card('followups.total', $followupTotal),
                $this->card('action_items.total', (clone $actions)->count()),
                $this->card('action_items.overdue', $overdue),
                $this->card('action_items.completion_rate', $completionRate, '%'),
                $this->card('exceptions.cancellation_applied', $exceptionCounts['CANCELLATION'] ?? 0),
                $this->card('exceptions.postponement_applied', $exceptionCounts['POSTPONEMENT'] ?? 0),
                $this->card('reschedules.confirmed', $reschedules),
                $this->card('participant_changes.confirmed', $participantChanges),
            ],
            'charts' => [
                ['metric_id' => 'meetings.status_distribution', 'labels' => array_keys($statusCounts), 'series' => array_values($statusCounts)],
                ['metric_id' => 'action_items.by_status', 'labels' => array_keys($actionStatusCounts), 'series' => array_values($actionStatusCounts)],
                ['metric_id' => 'exceptions.applied_by_type', 'labels' => array_keys($exceptionCounts), 'series' => array_values($exceptionCounts)],
            ],
            'summary' => [
                'meeting_status_counts' => $statusCounts,
                'action_item_status_counts' => $actionStatusCounts,
                'official_attendance_count' => $officialPresent,
                'invalidated_attendance_count' => $invalidatedAttendance,
                'expected_participant_count' => $expectedParticipants,
                'attendance_rate' => $attendanceRate,
                'followup_count' => $followupTotal,
                'followup_classification_counts' => (clone $followups)->select('meeting_decision_followups.classification', DB::raw('COUNT(*) AS total'))->groupBy('meeting_decision_followups.classification')->pluck('total', 'classification')->map(fn ($v) => (int) $v)->all(),
                'exception_event_counts' => $exceptionCounts,
            ],
        ];
    }

    public function meetings(ReportingScopeResolver $scope, ReportingFilter $filters): LengthAwarePaginator
    {
        return $this->meetingBase($scope, $filters)
            ->with(['request.requestingUnit', 'organizingUnit', 'units.unit', 'currentScheduleVersion', 'currentParticipantVersion', 'authorityContext'])
            ->select('meetings.*')
            ->orderBy('meetings.'.$filters->sort, $filters->direction)
            ->paginate($filters->perPage);
    }

    public function requests(ReportingScopeResolver $scope, ReportingFilter $filters): LengthAwarePaginator
    {
        return $this->requestBase($scope, $filters)
            ->with(['requestingUnit', 'meeting'])
            ->select('meeting_requests.*')
            ->orderBy('meeting_requests.'.$filters->sort, $filters->direction)
            ->paginate($filters->perPage);
    }

    public function attendance(ReportingScopeResolver $scope, ReportingFilter $filters): LengthAwarePaginator
    {
        $query = MeetingAttendanceRecord::query()->with(['meeting', 'employee.unit', 'participantVersion', 'invalidation'])
            ->whereIn('meeting_id', $this->meetingBase($scope, $filters)->select('meetings.id'))
            ->whereBetween('recorded_at_server', [$filters->dateRange->from->utc(), $filters->dateRange->toExclusive->utc()]);
        if ($filters->attendanceState === 'OFFICIAL') $query->whereDoesntHave('invalidation');
        if ($filters->attendanceState === 'INVALIDATED') $query->whereHas('invalidation');
        return $query->orderBy('recorded_at_server', $filters->direction)->paginate($filters->perPage);
    }

    public function decisions(ReportingScopeResolver $scope, ReportingFilter $filters): LengthAwarePaginator
    {
        return $this->decisionBase($scope, $filters)->with(['version.minutes.meeting', 'responsibleUnit', 'responsibleEmployee'])->paginate($filters->perPage);
    }

    public function actionItems(ReportingScopeResolver $scope, ReportingFilter $filters): LengthAwarePaginator
    {
        $query = $this->actionBase($scope, $filters)->with(['responsibleUnit', 'assignedEmployee', 'followup.decision', 'followup.meeting']);
        if ($filters->actionItemStatus) $query->where('meeting_action_items.status', $filters->actionItemStatus);
        if ($filters->overdue !== null) {
            $overdue = $filters->overdue;
            $query->where(function (Builder $q) use ($overdue, $filters): void {
                $condition = fn (Builder $inner) => $inner->whereDate('meeting_action_items.due_date', '<', CarbonImmutable::now($filters->dateRange->timezone)->toDateString())->whereNotIn('meeting_action_items.status', [MeetingActionItem::COMPLETED, MeetingActionItem::CANCELLED]);
                $overdue ? $q->where($condition) : $q->whereNot($condition);
            });
        }
        return $query->orderBy('meeting_action_items.'.$filters->sort, $filters->direction)->paginate($filters->perPage);
    }

    public function exceptions(ReportingScopeResolver $scope, ReportingFilter $filters): LengthAwarePaginator
    {
        $query = $this->exceptionBase($scope, $filters)->with(['meeting', 'currentVersion.reviews']);
        if ($filters->exceptionType) $query->where('meeting_exception_cases.case_type', $filters->exceptionType);
        return $query->orderBy('meeting_exception_cases.created_at', $filters->direction)->paginate($filters->perPage);
    }

    public function technical(ReportingScopeResolver $scope): array
    {
        $scope->requirePermission('reporting.technical.view');
        return [
            'scope' => 'TECHNICAL_INFRASTRUCTURE',
            'as_of' => now()->toIso8601String(),
            'definition_version' => ReportingMetricCatalog::DEFINITION_VERSION,
            'cards' => [
                $this->card('terminals.total', DB::table('terminals')->count()),
                $this->card('terminals.by_status', DB::table('terminals')->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->get()->mapWithKeys(fn ($row) => [$row->status => (int) $row->total])->all()),
                $this->card('terminal_sessions.active', DB::table('terminal_meeting_sessions')->where('status', 'ACTIVE')->count()),
                $this->card('terminal_assignments.active', DB::table('meeting_terminal_assignments')->whereIn('status', ['ASSIGNED', 'ACTIVE'])->count()),
            ],
        ];
    }

    private function meetingBase(ReportingScopeResolver $scope, ReportingFilter $filters): Builder
    {
        $query = Meeting::query()->whereBetween('meetings.created_at', [$filters->dateRange->from->utc(), $filters->dateRange->toExclusive->utc()]);
        $scope->constrainMeetings($query, $filters->unitId(), $filters->attribution);
        if ($filters->flowType) $query->whereHas('request', fn (Builder $q) => $q->where('flow_type', $filters->flowType));
        if ($filters->meetingStatus) $query->where('meetings.status', $filters->meetingStatus);
        if ($filters->highAuthorityOnly === true) $query->whereHas('authorityContext');
        if ($filters->search) $query->where(fn (Builder $q) => $q->where('meetings.meeting_number', 'like', '%'.$filters->search.'%')->orWhere('meetings.title', 'like', '%'.$filters->search.'%'));
        return $query;
    }

    private function requestBase(ReportingScopeResolver $scope, ReportingFilter $filters): Builder
    {
        $query = MeetingRequest::query()->whereBetween('meeting_requests.created_at', [$filters->dateRange->from->utc(), $filters->dateRange->toExclusive->utc()]);
        $scope->constrainRequests($query, $filters->requestingUnitId ?? $filters->involvedUnitId);
        if ($filters->flowType) $query->where('meeting_requests.flow_type', $filters->flowType);
        if ($filters->requestStatus) $query->where('meeting_requests.status', $filters->requestStatus);
        if ($filters->search) $query->where(fn (Builder $q) => $q->where('meeting_requests.request_number', 'like', '%'.$filters->search.'%')->orWhere('meeting_requests.subject', 'like', '%'.$filters->search.'%'));
        return $query;
    }

    private function decisionBase(ReportingScopeResolver $scope, ReportingFilter $filters): Builder
    {
        $query = MeetingMinuteVersionDecision::query()
            ->join('meeting_minute_versions', 'meeting_minute_versions.id', '=', 'meeting_minute_version_decisions.meeting_minute_version_id')
            ->join('meeting_minutes', function ($join): void {
                $join->on('meeting_minutes.current_version_id', '=', 'meeting_minute_versions.id')->where('meeting_minutes.status', 'FINALIZED');
            })
            ->join('meetings', 'meetings.id', '=', 'meeting_minutes.meeting_id')
            ->whereBetween('meeting_minutes.finalized_at', [$filters->dateRange->from->utc(), $filters->dateRange->toExclusive->utc()]);
        $scope->constrainJoinedMeetings($query, $filters->unitId(), $filters->attribution);
        return $query;
    }

    private function followupBase(ReportingScopeResolver $scope, ReportingFilter $filters): Builder
    {
        $query = MeetingDecisionFollowUp::query()->join('meetings', 'meetings.id', '=', 'meeting_decision_followups.meeting_id')->whereBetween('meeting_decision_followups.created_at', [$filters->dateRange->from->utc(), $filters->dateRange->toExclusive->utc()]);
        $scope->constrainJoinedMeetings($query, $filters->unitId(), $filters->attribution);
        if ($filters->followupStatus) $query->where('meeting_decision_followups.status', $filters->followupStatus);
        return $query;
    }

    private function actionBase(ReportingScopeResolver $scope, ReportingFilter $filters): Builder
    {
        $query = MeetingActionItem::query()->join('meeting_decision_followups', 'meeting_decision_followups.id', '=', 'meeting_action_items.decision_followup_id')->join('meetings', 'meetings.id', '=', 'meeting_decision_followups.meeting_id')->whereBetween('meeting_action_items.created_at', [$filters->dateRange->from->utc(), $filters->dateRange->toExclusive->utc()])->select('meeting_action_items.*');
        $scope->constrainActionItems($query, $filters->unitId());
        return $query;
    }

    private function exceptionBase(ReportingScopeResolver $scope, ReportingFilter $filters): Builder
    {
        $query = MeetingExceptionCase::query()->join('meetings', 'meetings.id', '=', 'meeting_exception_cases.meeting_id')->whereBetween('meeting_exception_cases.created_at', [$filters->dateRange->from->utc(), $filters->dateRange->toExclusive->utc()]);
        $scope->constrainJoinedMeetings($query, $filters->unitId(), $filters->attribution);
        return $query;
    }

    private function rescheduleCount(ReportingScopeResolver $scope, ReportingFilter $filters): int
    {
        $query = MeetingRescheduleCase::query()->join('meetings', 'meetings.id', '=', 'meeting_reschedule_cases.meeting_id')->whereNotNull('meeting_reschedule_cases.confirmed_at')->whereBetween('meeting_reschedule_cases.confirmed_at', [$filters->dateRange->from->utc(), $filters->dateRange->toExclusive->utc()]);
        $scope->constrainJoinedMeetings($query, $filters->unitId(), $filters->attribution);
        return $query->count();
    }

    private function participantChangeCount(ReportingScopeResolver $scope, ReportingFilter $filters): int
    {
        $query = MeetingParticipantChangeCase::query()->join('meetings', 'meetings.id', '=', 'meeting_participant_change_cases.meeting_id')->whereIn('meeting_participant_change_cases.status', ['CONFIRMED', 'APPLIED'])->whereBetween('meeting_participant_change_cases.updated_at', [$filters->dateRange->from->utc(), $filters->dateRange->toExclusive->utc()]);
        $scope->constrainJoinedMeetings($query, $filters->unitId(), $filters->attribution);
        return $query->count();
    }

    private function card(string $metricId, mixed $value, string $unit = 'count'): array
    {
        $definition = ReportingMetricCatalog::get($metricId);
        return ['metric_id' => $metricId, 'label' => $definition['label'], 'value' => $value, 'unit' => $unit, 'definition_version' => ReportingMetricCatalog::DEFINITION_VERSION];
    }

    private function scopeDescriptor(ReportingScopeResolver $scope, ReportingFilter $filters): array
    {
        return ['user_id' => $scope->user()->id, 'unit_ids' => $scope->unitIds(), 'organization_wide' => $scope->allowsOrganizationBusiness(), 'attribution_mode' => $filters->attribution];
    }
}
