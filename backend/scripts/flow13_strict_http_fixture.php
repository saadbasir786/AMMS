<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingTerminalAssignment;
use App\Models\MeetingUnit;
use App\Models\Organization;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\Position;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Str;

$owner = User::where('email', 'tech.coordinator@example.test')->firstOrFail();
$director = User::where('email', 'tech.director@example.test')->firstOrFail();
$high = User::where('email', 'high.authority@example.test')->firstOrFail();
$tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$fin = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
$hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
$authOffice = OrganizationalUnit::where('code', 'AUTH-OFFICE')->firstOrFail();

$make = function (string $flowType, array $unitRoles, int $status = 0, bool $authority = false) use ($owner, $director, $high, $tech, $authOffice): Meeting {
    $request = MeetingRequest::create([
        'request_number' => 'FLOW13-HTTP-'.strtoupper(Str::random(10)),
        'flow_type' => $flowType,
        'requesting_unit_id' => $flowType === 'HIGH_AUTHORITY_INITIATED' ? null : $tech->id,
        'created_by' => $owner->id,
        'subject' => 'جلسهٔ آزمون HTTP FLOW-13',
        'purpose' => 'COORDINATION',
        'status' => 'CONVERTED_TO_MEETING',
        'coordinator_id' => $owner->id,
    ]);
    $meeting = Meeting::create([
        'meeting_request_id' => $request->id,
        'meeting_number' => 'FLOW13-HTTP-M-'.strtoupper(Str::random(10)),
        'title' => $request->subject,
        'organizing_unit_id' => $flowType === 'HIGH_AUTHORITY_INITIATED' ? $authOffice->id : $tech->id,
        'coordinator_id' => $owner->id,
        'chairperson_employee_id' => $director->employee_id,
        'meeting_date' => now()->addDays(12)->format('Y-m-d'),
        'start_time' => '10:00',
        'end_time' => '11:00',
        'location' => 'تالار آزمون FLOW-13',
        'status' => $status === 2 ? 'COMPLETED' : 'SCHEDULED',
    ]);
    foreach ($unitRoles as [$unitId, $role]) MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unitId, 'role' => $role]);
    MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $director->employee_id, 'organizational_unit_id' => $tech->id, 'role' => 'PARTICIPATING']);
    $schedule = app(ScheduleVersionService::class)->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $owner->id);
    app(ScheduleVersionService::class)->applyToMeeting($meeting, $schedule);
    app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);
    if ($authority) {
        MeetingAuthorityContext::create(['meeting_id' => $meeting->id, 'organization_authority_id' => OrganizationAuthority::firstOrFail()->id, 'authority_type' => 'HIGH_AUTHORITY', 'authority_position_id' => Position::firstOrFail()->id, 'authority_employee_id' => $high->employee_id, 'authority_office_unit_id' => $authOffice->id]);
    }
    return $meeting->fresh(['request', 'currentScheduleVersion', 'currentParticipantVersion']);
};

$multi = $make('MULTI_DIRECTORATE', [[$tech->id, 'REQUESTING'], [$fin->id, 'PARTICIPATING'], [$hr->id, 'PARTICIPATING']]);
$authority = $make('MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', [[$tech->id, 'REQUESTING'], [$fin->id, 'PARTICIPATING'], [$hr->id, 'PARTICIPATING']], 0, true);
$attendance = $make('INTERNAL_DIRECTORATE', [[$tech->id, 'PARTICIPATING']]);
$completed = $make('INTERNAL_DIRECTORATE', [[$tech->id, 'PARTICIPATING']], 2);

$terminal = Terminal::create(['uuid' => (string) Str::uuid(), 'organization_id' => Organization::firstOrFail()->id, 'code' => 'FLOW13-HTTP-TERM-'.strtoupper(Str::random(8)), 'name' => 'FLOW-13 HTTP terminal', 'serial_number' => 'FLOW13-HTTP-SERIAL-'.strtoupper(Str::random(8)), 'manufacturer' => 'TEST', 'model' => 'FLOW13', 'platform' => 'TEST', 'os_version' => '1', 'app_version' => '1', 'status' => 'ACTIVE', 'registered_by_user_id' => $owner->id, 'registered_at' => now()]);
$assignment = MeetingTerminalAssignment::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $attendance->id, 'terminal_id' => $terminal->id, 'baseline_schedule_version_id' => $attendance->current_schedule_version_id, 'baseline_participant_version_id' => $attendance->current_participant_version_id, 'valid_from' => now()->subMinute(), 'valid_until' => now()->addHour(), 'status' => 'ASSIGNED', 'assigned_by_user_id' => $owner->id, 'assigned_at' => now()]);
$rawToken = 'flow13-http-session-'.Str::random(48);
$session = TerminalMeetingSession::create(['uuid' => (string) Str::uuid(), 'assignment_id' => $assignment->id, 'terminal_id' => $terminal->id, 'meeting_id' => $attendance->id, 'schedule_version_id' => $attendance->current_schedule_version_id, 'participant_version_id' => $attendance->current_participant_version_id, 'status' => 'ACTIVE', 'session_token_hash' => hash('sha256', $rawToken), 'activated_at' => now(), 'expires_at' => now()->addHour()]);
$terminal->forceFill(['current_session_id' => $session->id])->save();
$attendance->forceFill(['current_terminal_session_id' => $session->id])->save();

echo json_encode(['multi_meeting_id'=>$multi->id,'authority_meeting_id'=>$authority->id,'attendance_meeting_id'=>$attendance->id,'completed_meeting_id'=>$completed->id,'owner_email'=>$owner->email,'director_email'=>$director->email,'terminal_session_uuid'=>$session->uuid,'terminal_session_token'=>$rawToken,'terminal_assignment_id'=>$assignment->id,'terminal_session_id'=>$session->id,'attendance_employee_id'=>$director->employee_id], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
