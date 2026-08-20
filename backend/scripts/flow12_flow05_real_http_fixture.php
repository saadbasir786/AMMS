<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingExecutionSession;
use App\Models\MeetingMinuteVersion;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\MeetingMinutes;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingRequestUnit;
use App\Models\MeetingUnit;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
$officeUser = User::where('email', 'authority.office@example.test')->firstOrFail();
$authorityUser = User::where('email', 'high.authority@example.test')->firstOrFail();
$authorityEmployee = Employee::where('employee_number', 'EMP-AUTH-001')->firstOrFail();
$techEmployee = Employee::where('employee_number', 'EMP-TECH-003')->firstOrFail();
$financeEmployee = Employee::where('employee_number', 'EMP-FIN-001')->firstOrFail();
$tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
$office = OrganizationalUnit::where('code', 'AUTH-OFFICE')->firstOrFail();
$responsibleUnit = OrganizationalUnit::findOrFail($financeEmployee->organizational_unit_id);
$authority = OrganizationAuthority::firstOrFail();
$tag = 'F12-FLOW05-HTTP-'.Str::upper(Str::random(8));

$request = MeetingRequest::create([
    'request_number' => $tag.'-REQ',
    'flow_type' => 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY',
    'requesting_unit_id' => $tech->id,
    'created_by' => $secretary->id,
    'subject' => 'FLOW-05 real HTTP governance closure',
    'purpose' => 'DECISION',
    'description' => 'Fresh persisted true FLOW-05 verification fixture.',
    'meeting_justification' => 'Strict governance evidence.',
    'requested_outcome' => 'Completed decision follow-up closure.',
    'priority' => 'NORMAL',
    'status' => 'CONVERTED_TO_MEETING',
    'coordinator_id' => $secretary->id,
    'lead_coordinator_unit_id' => $tech->id,
    'lead_coordinator_user_id' => $secretary->id,
]);

foreach ([
    ['meeting_request_id' => $request->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING'],
    ['meeting_request_id' => $request->id, 'organizational_unit_id' => $finance->id, 'role' => 'PARTICIPATING'],
] as $requestUnit) MeetingRequestUnit::create($requestUnit);

$request->authorityContext()->create([
    'organization_authority_id' => $authority->id,
    'authority_type' => $authority->authority_type,
    'authority_office_unit_id' => $office->id,
    'authority_position_id' => $authority->authority_position_id,
    'authority_employee_id' => $authorityEmployee->id,
    'origin_type' => 'REQUESTED',
    'office_review_status' => 'COMPLETED',
    'office_reviewed_by' => $officeUser->id,
    'office_reviewed_at' => now()->subHours(4),
    'forwarded_by' => $officeUser->id,
    'forwarded_at' => now()->subHours(3),
    'authority_decision_status' => 'APPROVED',
    'authority_decided_by' => $authorityUser->id,
    'authority_decided_at' => now()->subHours(2),
]);

$meeting = Meeting::create([
    'meeting_request_id' => $request->id,
    'meeting_number' => $tag.'-MEETING',
    'title' => $request->subject,
    'description' => $request->description,
    'organizing_unit_id' => $tech->id,
    'coordinator_id' => $secretary->id,
    'chairperson_employee_id' => $authorityEmployee->id,
    'meeting_date' => now()->subDay()->format('Y-m-d'),
    'start_time' => '10:00',
    'end_time' => '11:00',
    'location' => 'دفتر مقام عالی',
    'agenda' => 'آجندای تصمیم و پیگیری',
    'status' => 'COMPLETED',
]);

foreach ([
    ['meeting_id' => $meeting->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING'],
    ['meeting_id' => $meeting->id, 'organizational_unit_id' => $finance->id, 'role' => 'PARTICIPATING'],
] as $meetingUnit) MeetingUnit::create($meetingUnit);

foreach ([
    ['meeting_id' => $meeting->id, 'employee_id' => $techEmployee->id, 'organizational_unit_id' => $tech->id, 'role' => 'REQUESTING'],
    ['meeting_id' => $meeting->id, 'employee_id' => $financeEmployee->id, 'organizational_unit_id' => $finance->id, 'role' => 'PARTICIPATING'],
    ['meeting_id' => $meeting->id, 'employee_id' => $authorityEmployee->id, 'organizational_unit_id' => $office->id, 'role' => 'HIGH_AUTHORITY'],
] as $participant) MeetingParticipant::create($participant);

$meetingAuthority = MeetingAuthorityContext::create([
    'meeting_id' => $meeting->id,
    'organization_authority_id' => $authority->id,
    'authority_type' => $authority->authority_type,
    'authority_position_id' => $authority->authority_position_id,
    'authority_employee_id' => $authorityEmployee->id,
    'authority_office_unit_id' => $office->id,
]);

$schedule = app(ScheduleVersionService::class);
$scheduleVersion = $schedule->createNext($meeting, [
    'meeting_date' => $meeting->meeting_date->format('Y-m-d'),
    'start_time' => '10:00',
    'end_time' => '11:00',
    'location' => $meeting->location,
], $secretary->id);
$schedule->applyToMeeting($meeting, $scheduleVersion);

$participantVersion = app(ParticipantVersionService::class)->ensureInitialVersion(
    $meeting->fresh(['request.authorityContext', 'participants.employee.unit']),
    $secretary->id
);

$session = MeetingExecutionSession::create([
    'uuid' => (string) Str::uuid(),
    'meeting_id' => $meeting->id,
    'schedule_version_id' => $scheduleVersion->id,
    'participant_version_id' => $participantVersion->id,
    'status' => 'ENDED',
    'started_at' => now()->subHours(2),
    'started_by_user_id' => $secretary->id,
    'ended_at' => now()->subHour(),
    'ended_by_user_id' => $secretary->id,
]);

$minutes = MeetingMinutes::create([
    'uuid' => (string) Str::uuid(),
    'meeting_id' => $meeting->id,
    'status' => MeetingMinutes::FINALIZED,
    'created_by_user_id' => $secretary->id,
    'finalized_at' => now()->subMinutes(45),
    'finalized_by_user_id' => $secretary->id,
]);
$version = MeetingMinuteVersion::create([
    'uuid' => (string) Str::uuid(),
    'meeting_minutes_id' => $minutes->id,
    'version_number' => 1,
    'execution_session_id' => $session->id,
    'schedule_version_id' => $scheduleVersion->id,
    'participant_version_id' => $participantVersion->id,
    'summary' => 'FLOW-05 finalized governance decision',
    'prepared_by_user_id' => $secretary->id,
    'submitted_at' => now()->subHour(),
]);
$decision = MeetingMinuteVersionDecision::create([
    'uuid' => (string) Str::uuid(),
    'meeting_minute_version_id' => $version->id,
    'sequence_number' => 1,
    'decision_number' => $tag.'-DEC-001',
    'decision_text' => 'تصمیم معتبر برای آزمون بسته‌شدن پیگیری ثبت شد.',
    'responsible_unit_id' => $finance->id,
    'responsible_employee_id' => $financeEmployee->id,
    'target_date' => now()->addDays(14)->format('Y-m-d'),
]);
$minutes->update(['current_version_id' => $version->id]);

$fixture = [
    'gate' => 'FLOW-12 TRUE FLOW-05 REAL-HTTP GOVERNANCE CLOSURE',
    'tag' => $tag,
    'fixture' => [
        'actual_flow_type' => $request->flow_type,
        'meeting_request_id' => $request->id,
        'request_number' => $request->request_number,
        'meeting_id' => $meeting->id,
        'meeting_number' => $meeting->meeting_number,
        'meeting_status' => $meeting->status,
        'requesting_unit' => ['id' => $tech->id, 'code' => $tech->code, 'name' => $tech->name, 'role' => 'REQUESTING'],
        'participating_units' => [['id' => $finance->id, 'code' => $finance->code, 'name' => $finance->name, 'role' => 'PARTICIPATING']],
        'high_authority_context' => [
            'meeting_authority_context_id' => $meetingAuthority->id,
            'organization_authority_id' => $authority->id,
            'employee_id' => $authorityEmployee->id,
            'employee_number' => $authorityEmployee->employee_number,
            'position_id' => $authority->authority_position_id,
            'office_unit_id' => $office->id,
            'office_unit_code' => $office->code,
            'office_unit_name' => $office->name,
        ],
        'minutes_id' => $minutes->id,
        'minutes_status' => $minutes->status,
        'finalized_minute_version_id' => $version->id,
        'decision_id' => $decision->id,
        'decision_number' => $decision->decision_number,
        'responsible_unit' => ['id' => $responsibleUnit->id, 'code' => $responsibleUnit->code, 'name' => $responsibleUnit->name],
        'responsible_unit_id' => $responsibleUnit->id,
        'responsible_employee_id' => $financeEmployee->id,
        'responsible_employee_email' => $financeEmployee->email,
        'executive_secretary_email' => $secretary->email,
    ],
    'source_snapshot' => [
        'request' => ['id' => $request->id, 'flow_type' => $request->flow_type, 'status' => $request->status, 'meeting_id' => $meeting->id],
        'meeting' => ['id' => $meeting->id, 'status' => $meeting->status, 'meeting_number' => $meeting->meeting_number],
        'minutes' => ['id' => $minutes->id, 'status' => $minutes->status, 'current_version_id' => $minutes->current_version_id],
        'version' => ['id' => $version->id, 'version_number' => $version->version_number, 'summary' => $version->summary, 'schedule_version_id' => $version->schedule_version_id, 'participant_version_id' => $version->participant_version_id],
        'decision' => ['id' => $decision->id, 'decision_number' => $decision->decision_number, 'decision_text' => $decision->decision_text, 'sequence_number' => $decision->sequence_number, 'responsible_unit_id' => $decision->responsible_unit_id, 'responsible_employee_id' => $decision->responsible_employee_id],
        'schedule_version_count' => $meeting->scheduleVersions()->count(),
        'participant_version_count' => $meeting->participantVersions()->count(),
        'meeting_count' => Meeting::where('id', $meeting->id)->count(),
        'minutes_count' => MeetingMinutes::where('id', $minutes->id)->count(),
        'decision_count' => MeetingMinuteVersionDecision::where('id', $decision->id)->count(),
    ],
];

file_put_contents(dirname(__DIR__, 2).'/flow12_flow05_real_http_fixture.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
