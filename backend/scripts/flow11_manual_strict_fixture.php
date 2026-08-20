<?php

declare(strict_types=1);

use App\Actions\Meetings\AssignTerminalToMeeting;
use App\Actions\Terminals\RegisterTerminal;
use App\Models\AmmsRoleScope;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAuthorityContext;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use App\Support\TerminalCredentialManager;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$coordinator = User::where('email', 'tech.exec@example.test')->firstOrFail();
$secretary = User::where('email', 'executive.secretary@example.test')->firstOrFail();
$authority = User::where('email', 'high.authority@example.test')->firstOrFail();
$office = User::where('email', 'authority.office@example.test')->firstOrFail();
$enrollmentOfficer = User::where('email', 'tech.exec@example.test')->firstOrFail();
$admin = User::where('email', 'system.admin@example.test')->firstOrFail();
$tech = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$finance = OrganizationalUnit::where('code', 'FIN')->firstOrFail();
$hr = OrganizationalUnit::where('code', 'HR')->firstOrFail();
$authorityOffice = OrganizationalUnit::where('code', 'AUTH-OFFICE')->firstOrFail();
$authorityEmployee = Employee::where('employee_number', 'EMP-AUTH-001')->firstOrFail();
$organizationAuthority = OrganizationAuthority::firstOrFail();
$directorEmployees = ['TECH' => Employee::where('email', 'tech.director@example.test')->firstOrFail(), 'FIN' => Employee::where('email', 'finance.director@example.test')->firstOrFail(), 'HR' => Employee::where('email', 'hr.director@example.test')->firstOrFail()];
$directorUsers = ['TECH' => User::where('email', 'tech.director@example.test')->firstOrFail(), 'FIN' => User::where('email', 'finance.director@example.test')->firstOrFail(), 'HR' => User::where('email', 'hr.director@example.test')->firstOrFail()];
$officerRole = Role::findByName('BIOMETRIC_ENROLLMENT_OFFICER', 'sanctum');
$enrollmentOfficer->assignRole($officerRole);
AmmsRoleScope::firstOrCreate(['user_id' => $enrollmentOfficer->id, 'role_id' => $officerRole->id, 'organizational_unit_id' => $tech->id]);

$makeMeeting = static function (string $tag, string $flowType, ?int $requestingUnitId, bool $authorityControlled) use ($coordinator, $secretary, $tech, $authorityOffice, $authorityEmployee, $organizationAuthority, $directorEmployees, $finance, $hr): Meeting {
    $owner = $authorityControlled ? $secretary : $coordinator;
    $request = MeetingRequest::create(['request_number' => 'FLOW11-MANUAL-'.$tag.'-'.Str::upper(Str::random(6)), 'flow_type' => $flowType, 'requesting_unit_id' => $requestingUnitId, 'created_by' => $owner->id, 'subject' => 'سناریوی دستی '.$tag.' FLOW-11', 'purpose' => 'STRICT_MANUAL_GATE', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
    $meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW11-MANUAL-'.$tag.'-M-'.Str::upper(Str::random(6)), 'title' => $request->subject, 'organizing_unit_id' => $authorityControlled ? $authorityOffice->id : $tech->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $authorityControlled ? $authorityEmployee->id : $directorEmployees['TECH']->id, 'meeting_date' => now()->addDays(25)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار manual FLOW-11', 'status' => 'SCHEDULED']);
    if ($authorityControlled) MeetingAuthorityContext::create(['meeting_id' => $meeting->id, 'organization_authority_id' => $organizationAuthority->id, 'authority_type' => $organizationAuthority->authority_type, 'authority_position_id' => $organizationAuthority->authority_position_id, 'authority_employee_id' => $authorityEmployee->id, 'authority_office_unit_id' => $authorityOffice->id]);
    foreach ([$tech, $finance, $hr] as $unit) MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
    foreach ($directorEmployees as $employee) MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
    $schedule = app(ScheduleVersionService::class)->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => $meeting->location], $owner->id);
    app(ScheduleVersionService::class)->applyToMeeting($meeting, $schedule);
    app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);
    return $meeting->fresh();
};

$meetings = [
    'flow03' => $makeMeeting('FLOW03', 'MULTI_DIRECTORATE', $tech->id, false),
    'flow05' => $makeMeeting('FLOW05', 'DIRECTORATE_WITH_HIGH_AUTHORITY', $tech->id, true),
    'flow06' => $makeMeeting('FLOW06', 'HIGH_AUTHORITY_INITIATED', null, true),
    'attendance' => $makeMeeting('ATTENDANCE', 'MULTI_DIRECTORATE', $tech->id, false),
];
$terminal = app(RegisterTerminal::class)->execute($admin, ['organization_id' => $tech->organization_id, 'code' => 'FLOW11-MANUAL-'.Str::upper(Str::random(8)), 'name' => 'FLOW-11 manual attendance terminal', 'status' => 'ACTIVE']);
$credential = app(TerminalCredentialManager::class)->issue($terminal, $admin);
$assignment = app(AssignTerminalToMeeting::class)->execute($coordinator, $meetings['attendance'], $terminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(40)->toIso8601String());
$out = ['base_url' => 'http://127.0.0.1:8012', 'accounts' => ['coordinator' => $coordinator->email, 'secretary' => $secretary->email, 'authority' => $authority->email, 'office' => $office->email, 'enrollment_officer' => $enrollmentOfficer->email, 'admin' => $admin->email], 'unit_ids' => ['TECH' => $tech->id, 'FIN' => $finance->id, 'HR' => $hr->id], 'meetings' => array_map(fn (Meeting $m): array => ['id' => $m->id, 'number' => $m->meeting_number, 'flow_type' => $m->request?->flow_type, 'requesting_unit_id' => $m->request?->requesting_unit_id], $meetings), 'attendance' => ['meeting_id' => $meetings['attendance']->id, 'terminal_id' => $terminal->id, 'terminal_credential' => $credential['token'], 'assignment_uuid' => $assignment->uuid, 'employee_id' => $directorEmployees['TECH']->id]];
file_put_contents('/tmp/flow11_manual_strict_fixture.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
