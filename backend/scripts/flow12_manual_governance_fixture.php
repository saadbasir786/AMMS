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
$authorityEmployee = Employee::where('employee_number', 'EMP-AUTH-001')->firstOrFail();
$techEmployee = Employee::where('email', 'tech.director@example.test')->firstOrFail();
$tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$office = OrganizationalUnit::where('code', 'AUTH-OFFICE')->firstOrFail();
$authority = OrganizationAuthority::firstOrFail();

$make = static function (string $flowType, bool $noRequester) use ($secretary, $authorityEmployee, $techEmployee, $tech, $office, $authority): array {
    $tag = 'F12-GOV-HTTP-'.Str::upper(Str::random(7));
    $request = MeetingRequest::create(['request_number' => $tag.'-REQ', 'flow_type' => $flowType, 'requesting_unit_id' => $noRequester ? null : OrganizationalUnit::where('code', 'TECH')->value('id'), 'created_by' => $secretary->id, 'subject' => 'FLOW-12 owner gate '.$flowType, 'purpose' => 'FLOW12_OWNER_GATE', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $secretary->id]);
    $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => $tag.'-MEETING', 'title' => $request->subject, 'organizing_unit_id' => $office->id, 'coordinator_id' => $secretary->id, 'chairperson_employee_id' => $authorityEmployee->id, 'meeting_date' => now()->subDay()->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW12-OWNER-GATE', 'status' => 'COMPLETED']);
    MeetingAuthorityContext::create(['meeting_id' => $meeting->id, 'organization_authority_id' => $authority->id, 'authority_type' => $authority->authority_type, 'authority_position_id' => $authority->authority_position_id, 'authority_employee_id' => $authorityEmployee->id, 'authority_office_unit_id' => $office->id]);
    MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $office->id, 'role' => 'PARTICIPATING']);
    MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $tech->id, 'role' => 'PARTICIPATING']);
    MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $authorityEmployee->id, 'organizational_unit_id' => $authorityEmployee->organizational_unit_id, 'role' => 'PARTICIPATING']);
    MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $techEmployee->id, 'organizational_unit_id' => $techEmployee->organizational_unit_id, 'role' => 'PARTICIPATING']);
    $schedule = app(ScheduleVersionService::class);
    $scheduleVersion = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => $meeting->location], $secretary->id);
    $schedule->applyToMeeting($meeting, $scheduleVersion);
    $participantVersion = app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $secretary->id);
    $session = MeetingExecutionSession::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'status' => 'ENDED', 'started_at' => now()->subHour(), 'started_by_user_id' => $secretary->id, 'ended_at' => now()->subMinutes(30), 'ended_by_user_id' => $secretary->id]);
    $minutes = MeetingMinutes::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'status' => MeetingMinutes::FINALIZED, 'created_by_user_id' => $secretary->id, 'finalized_at' => now()->subMinutes(10), 'finalized_by_user_id' => $secretary->id]);
    $version = MeetingMinuteVersion::create(['uuid' => (string) Str::uuid(), 'meeting_minutes_id' => $minutes->id, 'version_number' => 1, 'execution_session_id' => $session->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'summary' => 'FLOW-12 owner gate', 'prepared_by_user_id' => $secretary->id, 'submitted_at' => now()->subMinutes(20)]);
    $decision = MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $version->id, 'sequence_number' => 1, 'decision_number' => $flowType.'-DEC-001', 'decision_text' => 'تصمیم برای آزمون مالکیت ثبت شد.', 'responsible_unit_id' => $tech->id, 'responsible_employee_id' => $techEmployee->id, 'target_date' => now()->addDays(7)->format('Y-m-d')]);
    $minutes->update(['current_version_id' => $version->id]);
    return ['meeting_id' => $meeting->id, 'decision_id' => $decision->id, 'flow_type' => $flowType, 'no_requester' => $noRequester, 'responsible_unit_id' => $tech->id, 'responsible_employee_id' => $techEmployee->id, 'responsible_employee_email' => $techEmployee->email];
};
echo json_encode(['flow05' => $make('DIRECTORATE_WITH_HIGH_AUTHORITY', false), 'flow06' => $make('HIGH_AUTHORITY_INITIATED', true), 'owner_email' => $secretary->email, 'tag' => 'FLOW12-OWNER-GATE'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
