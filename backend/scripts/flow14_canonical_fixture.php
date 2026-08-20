<?php

declare(strict_types=1);

use App\Models\AmmsRoleScope;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemExtensionRequest;
use App\Models\MeetingAttendanceInvalidation;
use App\Models\MeetingAttendanceRecord;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingExceptionCase;
use App\Models\MeetingExceptionCaseVersion;
use App\Models\MeetingExecutionSession;
use App\Models\MeetingMinuteVersion;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\MeetingMinutes;
use App\Models\MeetingParticipant;
use App\Models\MeetingParticipantChangeCase;
use App\Models\MeetingParticipantChangeItem;
use App\Models\MeetingRequest;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingScheduleVersion;
use App\Models\MeetingUnit;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Spatie\Permission\Models\Role;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$owner = User::where('email', 'tech.coordinator@example.test')->firstOrFail();
$director = User::where('email', 'tech.director@example.test')->firstOrFail();
$highUser = User::where('email', 'high.authority@example.test')->firstOrFail();
$tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
$hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
$authorityOffice = OrganizationalUnit::where('code', 'AUTH-OFFICE')->firstOrFail();
$fixtureUnit = OrganizationalUnit::firstOrCreate(['code' => 'FLOW14-REPORTING'], ['organization_id' => $tech->organization_id, 'parent_id' => null, 'name' => 'FLOW-14 Reporting Fixture', 'unit_type' => 'DIRECTORATE', 'status' => 'ACTIVE']);
$employees = [
    'tech_director' => Employee::where('email', 'tech.director@example.test')->firstOrFail(),
    'tech_exec' => Employee::where('email', 'tech.exec@example.test')->firstOrFail(),
    'finance_exec' => Employee::where('email', 'finance.exec@example.test')->firstOrFail(),
    'hr_exec' => Employee::where('email', 'hr.exec@example.test')->firstOrFail(),
    'high_authority' => Employee::where('email', 'high.authority@example.test')->firstOrFail(),
];
$prefix = 'FLOW14-CANONICAL-';

$deleteWhereIn = static function (string $table, string $column, array $ids): void {
    if ($ids) DB::table($table)->whereIn($column, $ids)->delete();
};
$cleanup = static function () use ($prefix, $deleteWhereIn): void {
    $meetingIds = Meeting::where('meeting_number', 'like', $prefix.'M-%')->pluck('id')->all();
    $requestIds = MeetingRequest::where('request_number', 'like', $prefix.'R-%')->pluck('id')->all();
    if (!$meetingIds && !$requestIds) return;
    $followupIds = DB::table('meeting_decision_followups')->whereIn('meeting_id', $meetingIds)->pluck('id')->all();
    $actionIds = DB::table('meeting_action_items')->whereIn('decision_followup_id', $followupIds)->pluck('id')->all();
    $minuteVersionIds = DB::table('meeting_minute_versions as v')->join('meeting_minutes as m', 'm.id', '=', 'v.meeting_minutes_id')->whereIn('m.meeting_id', $meetingIds)->pluck('v.id')->all();
    $decisionIds = DB::table('meeting_minute_version_decisions')->whereIn('meeting_minute_version_id', $minuteVersionIds)->pluck('id')->all();
    $executionSessionIds = DB::table('meeting_execution_sessions')->whereIn('meeting_id', $meetingIds)->pluck('id')->all();
    $minutesIds = DB::table('meeting_minutes')->whereIn('meeting_id', $meetingIds)->pluck('id')->all();
    $attendanceIds = DB::table('meeting_attendance_records')->whereIn('meeting_id', $meetingIds)->pluck('id')->all();
    $exceptionIds = DB::table('meeting_exception_cases')->whereIn('meeting_id', $meetingIds)->pluck('id')->all();
    $exceptionVersionIds = DB::table('meeting_exception_case_versions')->whereIn('exception_case_id', $exceptionIds)->pluck('id')->all();
    $participantCaseIds = DB::table('meeting_participant_change_cases')->whereIn('meeting_id', $meetingIds)->pluck('id')->all();
    $participantVersionIds = DB::table('meeting_participant_versions')->whereIn('meeting_id', $meetingIds)->pluck('id')->all();
    $rescheduleCaseIds = DB::table('meeting_reschedule_cases')->whereIn('meeting_id', $meetingIds)->pluck('id')->all();
    $scheduleVersionIds = DB::table('meeting_schedule_versions')->whereIn('meeting_id', $meetingIds)->pluck('id')->all();
    $deleteWhereIn('meeting_action_item_evidence', 'action_item_id', $actionIds);
    $deleteWhereIn('meeting_action_item_updates', 'action_item_id', $actionIds);
    $deleteWhereIn('meeting_action_item_extension_requests', 'action_item_id', $actionIds);
    $deleteWhereIn('meeting_action_item_assignment_history', 'action_item_id', $actionIds);
    $deleteWhereIn('meeting_action_items', 'id', $actionIds);
    $deleteWhereIn('meeting_decision_followups', 'id', $followupIds);
    $deleteWhereIn('meeting_minute_version_decisions', 'id', $decisionIds);
    $deleteWhereIn('meeting_minute_version_items', 'meeting_minute_version_id', $minuteVersionIds);
    $deleteWhereIn('meeting_minute_reviews', 'meeting_minute_version_id', $minuteVersionIds);
    if ($minutesIds) DB::table('meeting_minutes')->whereIn('id', $minutesIds)->update(['current_version_id' => null]);
    $deleteWhereIn('meeting_minute_versions', 'id', $minuteVersionIds);
    $deleteWhereIn('meeting_minutes', 'id', $minutesIds);
    $deleteWhereIn('meeting_execution_sessions', 'id', $executionSessionIds);
    $deleteWhereIn('meeting_attendance_invalidations', 'meeting_attendance_record_id', $attendanceIds);
    $deleteWhereIn('meeting_attendance_records', 'id', $attendanceIds);
    $deleteWhereIn('meeting_exception_reviews', 'exception_case_version_id', $exceptionVersionIds);
    $deleteWhereIn('meeting_exception_case_versions', 'id', $exceptionVersionIds);
    $deleteWhereIn('meeting_exception_cases', 'id', $exceptionIds);
    $deleteWhereIn('meeting_participant_change_items', 'change_case_id', $participantCaseIds);
    $deleteWhereIn('meeting_participant_change_cases', 'id', $participantCaseIds);
    $deleteWhereIn('meeting_participant_version_members', 'participant_version_id', $participantVersionIds);
    $deleteWhereIn('meeting_participant_versions', 'id', $participantVersionIds);
    $deleteWhereIn('meeting_reschedule_acknowledgements', 'proposal_id', DB::table('meeting_reschedule_proposals')->whereIn('reschedule_case_id', $rescheduleCaseIds)->pluck('id')->all());
    $deleteWhereIn('meeting_reschedule_proposals', 'reschedule_case_id', $rescheduleCaseIds);
    $deleteWhereIn('meeting_reschedule_alternative_requests', 'reschedule_case_id', $rescheduleCaseIds);
    $deleteWhereIn('meeting_reschedule_cases', 'id', $rescheduleCaseIds);
    if ($meetingIds) DB::table('meetings')->whereIn('id', $meetingIds)->update(['current_schedule_version_id' => null, 'current_participant_version_id' => null, 'active_reschedule_case_id' => null, 'active_participant_change_case_id' => null]);
    $deleteWhereIn('meeting_schedule_versions', 'id', $scheduleVersionIds);
    $deleteWhereIn('meeting_authority_contexts', 'meeting_id', $meetingIds);
    $deleteWhereIn('meeting_units', 'meeting_id', $meetingIds);
    $deleteWhereIn('meeting_participants', 'meeting_id', $meetingIds);
    $deleteWhereIn('meeting_actions', 'meeting_id', $meetingIds);
    $deleteWhereIn('meeting_request_participants', 'meeting_request_id', $requestIds);
    $deleteWhereIn('meeting_request_actions', 'meeting_request_id', $requestIds);
    $deleteWhereIn('meetings', 'id', $meetingIds);
    $deleteWhereIn('meeting_requests', 'id', $requestIds);
};

DB::transaction(function () use ($cleanup, $owner, $director, $highUser, $tech, $finance, $hr, $authorityOffice, $fixtureUnit, $employees, $prefix): void {
    $cleanup();
    $scheduleService = app(ScheduleVersionService::class);
    $participantService = app(ParticipantVersionService::class);
    $now = now();
    $unitByCode = ['TECH' => $tech, 'FIN' => $finance, 'HR' => $hr];
    $directorRole = Role::where('name', 'DIRECTOR')->where('guard_name', 'sanctum')->firstOrFail();
    AmmsRoleScope::firstOrCreate(['user_id' => $director->id, 'role_id' => $directorRole->id, 'organizational_unit_id' => $fixtureUnit->id]);
    $requestKeys = [];
    $meetingKeys = [];

    $createRequest = static function (string $key, string $flowType, string $status, OrganizationalUnit $unit) use (&$requestKeys, $owner, $prefix, $fixtureUnit): MeetingRequest {
        $request = MeetingRequest::create([
            'request_number' => $prefix.'R-'.$key,
            'flow_type' => $flowType,
            'requesting_unit_id' => $fixtureUnit->id,
            'created_by' => $owner->id,
            'subject' => 'FLOW-14 canonical '.$key,
            'purpose' => 'REPORTING_FIXTURE',
            'description' => 'Deterministic FLOW-14 verification fixture.',
            'priority' => 'NORMAL',
            'preferred_date' => now()->addDays(7)->toDateString(),
            'preferred_start_time' => '09:00',
            'estimated_duration_minutes' => 60,
            'proposed_location' => 'تالار گزارش‌دهی',
            'status' => $status,
            'coordinator_id' => $owner->id,
        ]);
        $requestKeys[$key] = $request->id;
        return $request;
    };

    foreach ([
        ['m-scheduled-1', 'INTERNAL_DIRECTORATE', 'SCHEDULED', $tech, '2026-09-01'],
        ['m-scheduled-2', 'INTER_DIRECTORATE', 'SCHEDULED', $finance, '2026-09-02'],
        ['m-postponed', 'MULTI_DIRECTORATE', 'POSTPONED', $tech, '2026-08-28'],
        ['m-cancelled', 'HIGH_AUTHORITY', 'CANCELLED', $authorityOffice, '2026-08-27'],
        ['m-in-progress', 'AUTHORITY_INITIATED', 'IN_PROGRESS', $hr, '2026-08-26'],
        ['m-held', 'INTERNAL_DIRECTORATE', 'HELD', $tech, '2026-08-24'],
        ['m-completed-1', 'MULTI_DIRECTORATE', 'COMPLETED', $tech, '2026-08-23'],
        ['m-completed-2', 'HIGH_AUTHORITY', 'COMPLETED', $authorityOffice, '2026-08-22'],
        ['m-scheduled-3', 'INTER_DIRECTORATE', 'SCHEDULED', $hr, '2026-09-03'],
    ] as [$key, $flow, $status, $unit, $date]) {
        $request = $createRequest($key, $flow, 'CONVERTED_TO_MEETING', $unit);
        $chair = $status === 'COMPLETED' && $key === 'm-completed-2' ? $employees['high_authority'] : ($unit->code === 'FIN' ? $employees['finance_exec'] : ($unit->code === 'HR' ? $employees['hr_exec'] : $employees['tech_director']));
        $meeting = Meeting::create([
            'meeting_request_id' => $request->id,
            'meeting_number' => $prefix.'M-'.strtoupper(str_replace('-', '-', $key)),
            'title' => 'جلسهٔ تثبیت گزارش FLOW-14 '.$key,
            'description' => 'Canonical reporting fixture row.',
            'organizing_unit_id' => $fixtureUnit->id,
            'coordinator_id' => $owner->id,
            'chairperson_employee_id' => $chair->id,
            'meeting_date' => $date,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'location' => 'تالار مرکزی',
            'agenda' => 'گزارش‌دهی و سنجش رسمی',
            'status' => $status,
        ]);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $fixtureUnit->id, 'role' => 'PARTICIPATING']);
        MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
        if ($flow === 'MULTI_DIRECTORATE') {
            MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $finance->id, 'role' => 'PARTICIPATING']);
            MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $hr->id, 'role' => 'PARTICIPATING']);
        }
        $participants = [$chair];
        if ($key === 'm-held') $participants[] = $employees['tech_exec'];
        if ($flow === 'MULTI_DIRECTORATE') $participants[] = $employees['finance_exec'];
        foreach (array_unique(array_map(fn ($employee) => $employee->id, $participants)) as $employeeId) {
            $employee = Employee::findOrFail($employeeId);
            MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
        }
        $schedule = $scheduleService->createNext($meeting, ['meeting_date' => $date, 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار مرکزی'], $owner->id);
        $scheduleService->applyToMeeting($meeting, $schedule);
        $participantVersion = $participantService->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);
        $meetingKeys[$key] = ['id' => $meeting->id, 'request_id' => $request->id, 'schedule_id' => $schedule->id, 'participant_version_id' => $participantVersion->id, 'unit_id' => $unit->id];
    }

    foreach ([
        ['r-rejected-1', 'INTER_DIRECTORATE', 'REJECTED', $finance],
        ['r-returned-1', 'MULTI_DIRECTORATE', 'RETURNED_FOR_REVISION', $hr],
        ['r-pending-1', 'HIGH_AUTHORITY', 'PENDING_INTERNAL_APPROVAL', $authorityOffice],
        ['r-rejected-2', 'AUTHORITY_INITIATED', 'REJECTED', $tech],
        ['r-returned-2', 'INTERNAL_DIRECTORATE', 'RETURNED_FOR_REVISION', $finance],
    ] as [$key, $flow, $status, $unit]) $createRequest($key, $flow, $status, $unit);

    $highAuthorityRecord = OrganizationAuthority::query()->firstOrFail();
    MeetingAuthorityContext::create(['meeting_id' => $meetingKeys['m-completed-2']['id'], 'organization_authority_id' => $highAuthorityRecord->id, 'authority_type' => 'HIGH_AUTHORITY', 'authority_position_id' => $employees['high_authority']->position_id, 'authority_employee_id' => $employees['high_authority']->id, 'authority_office_unit_id' => $authorityOffice->id]);

    $reschedule = MeetingRescheduleCase::create(['meeting_id' => $meetingKeys['m-scheduled-2']['id'], 'baseline_schedule_version_id' => $meetingKeys['m-scheduled-2']['schedule_id'], 'initiated_by_user_id' => $owner->id, 'initiated_by_unit_id' => $fixtureUnit->id, 'reason' => 'FLOW-14 deterministic FLOW-07 history', 'governance_mode' => 'UNIT', 'status' => 'CONFIRMED', 'confirmed_at' => $now->copy()->subDays(2)]);
    $meeting = Meeting::findOrFail($meetingKeys['m-scheduled-2']['id']);
    $schedule2 = $scheduleService->createNext($meeting, ['meeting_date' => '2026-09-04', 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار مرکزی'], $owner->id, $reschedule);
    $scheduleService->applyToMeeting($meeting, $schedule2);

    $participantCase = MeetingParticipantChangeCase::create(['meeting_id' => $meetingKeys['m-scheduled-1']['id'], 'owner_type' => 'UNIT', 'owner_unit_id' => $fixtureUnit->id, 'baseline_participant_version_id' => $meetingKeys['m-scheduled-1']['participant_version_id'], 'baseline_schedule_version_id' => $meetingKeys['m-scheduled-1']['schedule_id'], 'initiated_by_user_id' => $owner->id, 'reason' => 'FLOW-14 deterministic FLOW-08 history', 'status' => 'CONFIRMED', 'submitted_at' => $now->copy()->subDays(2), 'confirmed_at' => $now->copy()->subDay()]);
    MeetingParticipantChangeItem::create(['change_case_id' => $participantCase->id, 'operation' => 'ADD', 'old_employee_id' => null, 'new_employee_id' => $employees['hr_exec']->id, 'participant_role' => 'PARTICIPATING']);
    $meeting = Meeting::findOrFail($meetingKeys['m-scheduled-1']['id'])->load('currentParticipantVersion');
    $memberRows = $meeting->currentParticipantVersion->members->map(fn ($member) => ['employee_id' => $member->employee_id, 'organizational_unit_id' => $member->organizational_unit_id, 'participant_role' => $member->participant_role, 'owner_type' => $member->owner_type, 'owner_unit_id' => $member->owner_unit_id, 'is_mandatory' => $member->is_mandatory])->values()->all();
    $memberRows[] = ['employee_id' => $employees['hr_exec']->id, 'organizational_unit_id' => $hr->id, 'participant_role' => 'PARTICIPATING', 'owner_type' => 'UNIT', 'owner_unit_id' => $hr->id, 'is_mandatory' => false];
    $participantService->createConfirmedVersion($meeting->fresh(['currentParticipantVersion', 'participants']), $participantCase->load('items'), $memberRows, $owner->id);

    $createAppliedException = static function (Meeting $meeting, string $key, string $type, string $baselineStatus, int $ownerId, int $scheduleId, int $participantId, ?string $resumeDate = null) use ($prefix): MeetingExceptionCase {
        $case = MeetingExceptionCase::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'case_type' => $type, 'status' => MeetingExceptionCase::APPLIED, 'governance_mode' => 'DIRECTOR', 'baseline_meeting_status' => $baselineStatus, 'baseline_schedule_version_id' => $scheduleId, 'baseline_participant_version_id' => $participantId, 'created_by_user_id' => $ownerId, 'created_at' => now()->subDays(3), 'applied_by_user_id' => $ownerId, 'applied_at' => now()->subDays(2), 'active_marker' => null]);
        $version = MeetingExceptionCaseVersion::create(['uuid' => (string) Str::uuid(), 'exception_case_id' => $case->id, 'version_number' => 1, 'reason' => 'FLOW-14 deterministic '.$key, 'administrative_note' => 'Canonical evidence only.', 'replacement_expected' => $type === MeetingExceptionCase::RESUMPTION, 'proposed_resume_date' => $resumeDate, 'baseline_meeting_status' => $baselineStatus, 'baseline_schedule_version_id' => $scheduleId, 'baseline_participant_version_id' => $participantId, 'prepared_by_user_id' => $ownerId, 'submitted_at' => now()->subDays(3), 'created_at' => now()->subDays(3)]);
        $case->update(['current_version_id' => $version->id]);
        return $case;
    };
    $scheduled1 = Meeting::findOrFail($meetingKeys['m-scheduled-1']['id']);
    $cancel = $createAppliedException($scheduled1, 'scheduled-chain-cancel', MeetingExceptionCase::CANCELLATION, 'SCHEDULED', $owner->id, $meetingKeys['m-scheduled-1']['schedule_id'], $meetingKeys['m-scheduled-1']['participant_version_id']);
    $reopen = $createAppliedException($scheduled1, 'scheduled-chain-reopen', MeetingExceptionCase::REOPENING, 'CANCELLED', $owner->id, $meetingKeys['m-scheduled-1']['schedule_id'], $meetingKeys['m-scheduled-1']['participant_version_id']);
    $resume = $createAppliedException($scheduled1, 'scheduled-chain-resume', MeetingExceptionCase::RESUMPTION, 'POSTPONED', $owner->id, $meetingKeys['m-scheduled-1']['schedule_id'], $meetingKeys['m-scheduled-1']['participant_version_id'], '2026-09-05');
    $postponed = Meeting::findOrFail($meetingKeys['m-postponed']['id']);
    $postpone = $createAppliedException($postponed, 'postponed', MeetingExceptionCase::POSTPONEMENT, 'SCHEDULED', $owner->id, $meetingKeys['m-postponed']['schedule_id'], $meetingKeys['m-postponed']['participant_version_id']);
    $cancelled = Meeting::findOrFail($meetingKeys['m-cancelled']['id']);
    $cancelledCase = $createAppliedException($cancelled, 'cancelled', MeetingExceptionCase::CANCELLATION, 'SCHEDULED', $owner->id, $meetingKeys['m-cancelled']['schedule_id'], $meetingKeys['m-cancelled']['participant_version_id']);

    foreach ([$scheduled1, $postponed, $cancelled] as $meeting) $meeting->update(['status' => match ($meeting->id) { $postponed->id => 'POSTPONED', $cancelled->id => 'CANCELLED', default => 'SCHEDULED' }]);
    $resumptionSchedule = $scheduleService->createNext($scheduled1->fresh(), ['meeting_date' => '2026-09-05', 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار مرکزی'], $owner->id);
    $scheduleService->applyToMeeting($scheduled1->fresh(), $resumptionSchedule);

    $executionMeetings = ['m-held', 'm-completed-1', 'm-completed-2'];
    foreach ($executionMeetings as $key) {
        $meeting = Meeting::findOrFail($meetingKeys[$key]['id']);
        MeetingExecutionSession::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'schedule_version_id' => $meeting->current_schedule_version_id, 'participant_version_id' => $meeting->current_participant_version_id, 'status' => 'ENDED', 'started_at' => now()->subDays(5), 'started_by_user_id' => $owner->id, 'ended_at' => now()->subDays(5)->addHour(), 'ended_by_user_id' => $owner->id, 'start_notes' => 'FLOW-14 fixture', 'end_notes' => 'FLOW-14 fixture']);
    }
    $completed1 = Meeting::findOrFail($meetingKeys['m-completed-1']['id']);
    $session1 = $completed1->executionSession()->firstOrFail();
    $minutes1 = MeetingMinutes::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $completed1->id, 'status' => MeetingMinutes::FINALIZED, 'created_by_user_id' => $owner->id, 'finalized_at' => now()->subDays(4), 'finalized_by_user_id' => $owner->id]);
    $v1 = MeetingMinuteVersion::create(['uuid' => (string) Str::uuid(), 'meeting_minutes_id' => $minutes1->id, 'version_number' => 1, 'execution_session_id' => $session1->id, 'schedule_version_id' => $completed1->current_schedule_version_id, 'participant_version_id' => $completed1->current_participant_version_id, 'summary' => 'نسخهٔ اصلاح‌نشده', 'prepared_by_user_id' => $owner->id, 'submitted_at' => now()->subDays(5)]);
    MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $v1->id, 'sequence_number' => 1, 'decision_number' => 'FLOW14-SUPERSEDED-001', 'decision_text' => 'تصمیم superseded برای آزمون تاریخچه.', 'responsible_unit_id' => $fixtureUnit->id, 'responsible_employee_id' => $employees['tech_director']->id, 'target_date' => '2026-09-15', 'notes' => 'Superseded v1']);
    $v2 = MeetingMinuteVersion::create(['uuid' => (string) Str::uuid(), 'meeting_minutes_id' => $minutes1->id, 'version_number' => 2, 'execution_session_id' => $session1->id, 'schedule_version_id' => $completed1->current_schedule_version_id, 'participant_version_id' => $completed1->current_participant_version_id, 'summary' => 'نسخهٔ اصلاح‌شده و نهایی', 'prepared_by_user_id' => $owner->id, 'submitted_at' => now()->subDays(4)]);
    $d1 = MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $v2->id, 'sequence_number' => 1, 'decision_number' => 'FLOW14-DEC-001', 'decision_text' => 'تصمیم نهایی اول.', 'responsible_unit_id' => $fixtureUnit->id, 'responsible_employee_id' => $employees['tech_director']->id, 'target_date' => '2026-09-20', 'notes' => 'Authoritative']);
    $d2 = MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $v2->id, 'sequence_number' => 2, 'decision_number' => 'FLOW14-DEC-002', 'decision_text' => 'تصمیم نهایی دوم.', 'responsible_unit_id' => $fixtureUnit->id, 'responsible_employee_id' => $employees['finance_exec']->id, 'target_date' => '2026-09-22', 'notes' => 'Authoritative']);
    $minutes1->update(['current_version_id' => $v2->id]);
    $noAction = MeetingDecisionFollowUp::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $completed1->id, 'meeting_minutes_id' => $minutes1->id, 'meeting_minute_version_id' => $v2->id, 'meeting_decision_id' => $d1->id, 'decision_number' => $d1->decision_number, 'classification' => MeetingDecisionFollowUp::NO_ACTION_REQUIRED, 'status' => MeetingDecisionFollowUp::CLOSED, 'classified_by_user_id' => $owner->id, 'classified_at' => now()->subDays(3), 'no_action_reason' => 'تصمیم برای اطلاع است.']);
    $actionFollowup = MeetingDecisionFollowUp::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $completed1->id, 'meeting_minutes_id' => $minutes1->id, 'meeting_minute_version_id' => $v2->id, 'meeting_decision_id' => $d2->id, 'decision_number' => $d2->decision_number, 'classification' => MeetingDecisionFollowUp::ACTION_REQUIRED, 'status' => MeetingDecisionFollowUp::OPEN, 'classified_by_user_id' => $owner->id, 'classified_at' => now()->subDays(3)]);
    $statuses = [MeetingActionItem::OPEN, MeetingActionItem::OPEN, MeetingActionItem::IN_PROGRESS, MeetingActionItem::BLOCKED, MeetingActionItem::PENDING_VERIFICATION, MeetingActionItem::COMPLETED, MeetingActionItem::COMPLETED, MeetingActionItem::COMPLETED, MeetingActionItem::CANCELLED, MeetingActionItem::CANCELLED];
    foreach ($statuses as $index => $status) {
        $past = $index < 4;
        $item = MeetingActionItem::create(['uuid' => (string) Str::uuid(), 'decision_followup_id' => $actionFollowup->id, 'sequence_number' => $index + 1, 'title' => 'اقدام تثبیت‌شده FLOW-14 '.($index + 1), 'description' => 'Canonical Action Item.', 'responsible_unit_id' => $fixtureUnit->id, 'assigned_employee_id' => $index % 2 === 0 ? $employees['tech_director']->id : $employees['finance_exec']->id, 'status' => $status, 'progress_percent' => $status === MeetingActionItem::COMPLETED ? 100 : ($status === MeetingActionItem::OPEN ? 0 : 50), 'due_date' => $past ? now()->subDays(2)->toDateString() : now()->addDays(8)->toDateString(), 'started_at' => $status !== MeetingActionItem::OPEN ? now()->subDays(3) : null, 'completed_at' => $status === MeetingActionItem::COMPLETED ? now()->subDay() : null, 'cancelled_at' => $status === MeetingActionItem::CANCELLED ? now()->subDay() : null, 'created_by_user_id' => $owner->id, 'lock_version' => 1]);
        if ($index === 0) MeetingActionItemExtensionRequest::create(['uuid' => (string) Str::uuid(), 'action_item_id' => $item->id, 'current_due_date' => now()->subDays(2)->toDateString(), 'requested_due_date' => now()->addDays(8)->toDateString(), 'reason' => 'تمدید مصوب برای آزمون.', 'status' => MeetingActionItemExtensionRequest::APPROVED, 'pending_marker' => null, 'requested_by_user_id' => $owner->id, 'requested_at' => now()->subDays(3), 'reviewed_by_user_id' => $director->id, 'reviewed_at' => now()->subDays(2), 'review_reason' => 'Approved']);
    }
    $completed2 = Meeting::findOrFail($meetingKeys['m-completed-2']['id']);
    $session2 = $completed2->executionSession()->firstOrFail();
    $minutes2 = MeetingMinutes::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $completed2->id, 'status' => MeetingMinutes::FINALIZED, 'created_by_user_id' => $owner->id, 'finalized_at' => now()->subDays(4), 'finalized_by_user_id' => $owner->id]);
    $vAuthority = MeetingMinuteVersion::create(['uuid' => (string) Str::uuid(), 'meeting_minutes_id' => $minutes2->id, 'version_number' => 1, 'execution_session_id' => $session2->id, 'schedule_version_id' => $completed2->current_schedule_version_id, 'participant_version_id' => $completed2->current_participant_version_id, 'summary' => 'صورت‌جلسهٔ مقام عالی', 'prepared_by_user_id' => $owner->id, 'submitted_at' => now()->subDays(4)]);
    $minutes2->update(['current_version_id' => $vAuthority->id]);

    $held = Meeting::findOrFail($meetingKeys['m-held']['id']);
    $heldPv = $held->current_participant_version_id;
    $attendance1 = MeetingAttendanceRecord::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $held->id, 'employee_id' => $employees['tech_director']->id, 'participant_version_id' => $heldPv, 'attendance_method' => 'MANUAL', 'client_event_uuid' => (string) Str::uuid(), 'recorded_at_server' => now()->subDays(5)->addMinutes(10), 'recorded_by_user_id' => $owner->id, 'manual_reason' => 'حضور رسمی canonical']);
    $attendance2 = MeetingAttendanceRecord::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $held->id, 'employee_id' => $employees['tech_exec']->id, 'participant_version_id' => $heldPv, 'attendance_method' => 'MANUAL', 'client_event_uuid' => (string) Str::uuid(), 'recorded_at_server' => now()->subDays(5)->addMinutes(11), 'recorded_by_user_id' => $owner->id, 'manual_reason' => 'رکورد باطل‌شده canonical']);
    MeetingAttendanceInvalidation::create(['uuid' => (string) Str::uuid(), 'meeting_attendance_record_id' => $attendance2->id, 'meeting_id' => $held->id, 'exception_case_id' => $cancelledCase->id, 'reason' => MeetingAttendanceInvalidation::CANCELLED, 'invalidated_at' => now()->subDays(2), 'invalidated_by_user_id' => $owner->id, 'created_at' => now()->subDays(2)]);

    $snapshot = [];
    foreach ($meetingKeys as $key => $row) {
        $meeting = Meeting::with(['request', 'scheduleVersions', 'participantVersions', 'attendanceRecords', 'minutes.versions', 'exceptionCases'])->findOrFail($row['id']);
        $snapshot[$key] = ['meeting' => $meeting->only(['id','meeting_number','status','meeting_request_id','current_schedule_version_id','current_participant_version_id']), 'request' => $meeting->request?->only(['id','request_number','status','flow_type']), 'schedule_versions' => $meeting->scheduleVersions->map->only(['id','version_number','meeting_date','start_time','end_time','location'])->values()->all(), 'participant_version_ids' => $meeting->participantVersions->pluck('id')->values()->all(), 'attendance_ids' => $meeting->attendanceRecords->pluck('id')->values()->all(), 'exception_types' => $meeting->exceptionCases->pluck('case_type')->values()->all()];
    }
    file_put_contents(dirname(__DIR__, 2).'/flow14_canonical_fixture.json', json_encode(['fixture_key' => $prefix, 'scope_unit_id' => $fixtureUnit->id, 'database_driver' => DB::connection()->getDriverName(), 'expected' => ['request_total' => 14, 'meeting_distinct_total' => 9, 'meeting_status_counts' => ['SCHEDULED' => 3, 'POSTPONED' => 1, 'CANCELLED' => 1, 'IN_PROGRESS' => 1, 'HELD' => 1, 'COMPLETED' => 2], 'conducted_meeting_count' => 3, 'upcoming_meeting_count' => 3, 'high_authority_meeting_count' => 1, 'finalized_decision_count' => 2, 'followup_count' => 2, 'followup_classification_counts' => ['NO_ACTION_REQUIRED' => 1, 'ACTION_REQUIRED' => 1], 'action_item_status_counts' => ['OPEN' => 2, 'IN_PROGRESS' => 1, 'BLOCKED' => 1, 'PENDING_VERIFICATION' => 1, 'COMPLETED' => 3, 'CANCELLED' => 2], 'action_item_overdue_count' => 4, 'action_item_completion_rate' => 37.5, 'official_attendance_count' => 1, 'invalidated_attendance_count' => 1, 'attendance_rate' => 50.0, 'postponement_event_count' => 1, 'cancellation_event_count' => 2, 'reopening_event_count' => 1, 'resumption_event_count' => 1, 'reschedule_confirmed_count' => 1, 'participant_change_confirmed_count' => 1], 'keys' => $meetingKeys, 'source_snapshot' => $snapshot], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL);
});

echo file_get_contents(dirname(__DIR__, 2).'/flow14_canonical_fixture.json');
