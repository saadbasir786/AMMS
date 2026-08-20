<?php

declare(strict_types=1);

use App\Actions\Meetings\AssignTerminalToMeeting;
use App\Actions\Terminals\RegisterTerminal;
use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\Terminal;
use App\Models\User;
use App\Models\AmmsRoleScope;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use App\Support\TerminalCredentialManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$actor = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
$coordinator = User::query()->where('email', 'tech.exec@example.test')->firstOrFail();
$admin = User::query()->where('email', 'system.admin@example.test')->firstOrFail();
$employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
$secondEmployee = Employee::query()->where('email', 'employee.a@example.test')->firstOrFail();
$unit = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();

$role = Role::findByName('BIOMETRIC_ENROLLMENT_OFFICER', 'sanctum');
$actor->assignRole($role);
AmmsRoleScope::firstOrCreate([
    'user_id' => $actor->id,
    'role_id' => $role->id,
    'organizational_unit_id' => $employee->organizational_unit_id,
]);

$terminals = [];
$credentials = [];
for ($i = 1; $i <= 3; $i++) {
    $terminals[$i] = app(RegisterTerminal::class)->execute($admin, [
        'organization_id' => $unit->organization_id,
        'code' => 'FLOW10A-MANUAL-'.$i.'-'.Str::upper(Str::random(8)),
        'name' => 'FLOW-10A Manual Evidence Terminal '.$i,
        'status' => 'ACTIVE',
    ]);
    $credentials[$i] = app(TerminalCredentialManager::class)->issue($terminals[$i], $admin);
}
$terminal = $terminals[1];
$credential = $credentials[1];

$request = MeetingRequest::create([
    'request_number' => 'FLOW10A-MANUAL-'.Str::upper(Str::random(8)),
    'flow_type' => 'MULTI_DIRECTORATE',
    'requesting_unit_id' => $unit->id,
    'created_by' => $actor->id,
    'subject' => 'FLOW-10A Manual Evidence Meeting',
    'purpose' => 'Manual software gate evidence',
    'status' => 'CONVERTED_TO_MEETING',
    'coordinator_id' => $coordinator->id,
]);
$meeting = Meeting::create([
    'meeting_request_id' => $request->id,
    'meeting_number' => 'FLOW10A-MANUAL-M-'.Str::upper(Str::random(8)),
    'title' => $request->subject,
    'organizing_unit_id' => $unit->id,
    'coordinator_id' => $coordinator->id,
    'chairperson_employee_id' => $employee->id,
    'meeting_date' => now()->addDays(30)->format('Y-m-d'),
    'start_time' => '10:00',
    'end_time' => '11:00',
    'location' => 'FLOW10A-MANUAL',
    'status' => 'SCHEDULED',
]);
MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $secondEmployee->id, 'organizational_unit_id' => $secondEmployee->organizational_unit_id, 'role' => 'PARTICIPATING']);
$schedule = app(ScheduleVersionService::class)->createNext($meeting, [
    'meeting_date' => $meeting->meeting_date->format('Y-m-d'),
    'start_time' => '10:00',
    'end_time' => '11:00',
    'location' => 'FLOW10A-MANUAL',
], $actor->id);
app(ScheduleVersionService::class)->applyToMeeting($meeting, $schedule);
app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $actor->id);
$meeting = $meeting->fresh();
$assignments = [];
foreach ($terminals as $index => $manualTerminal) {
    $assignments[$index] = app(AssignTerminalToMeeting::class)->execute($actor, $meeting, $manualTerminal, now()->subMinutes(2)->toIso8601String(), now()->addDays(40)->toIso8601String());
}
$assignment = $assignments[1];

file_put_contents('/tmp/flow10a_manual_fixture.json', json_encode([
    'base_url' => 'http://127.0.0.1:8010',
    'actor_email' => $actor->email,
    'admin_email' => $admin->email,
    'coordinator_email' => $coordinator->email,
    'employee_id' => $employee->id,
    'employee_email' => $employee->email,
    'second_employee_id' => $secondEmployee->id,
    'meeting_id' => $meeting->id,
    'meeting_number' => $meeting->meeting_number,
    'meeting_request_id' => $request->id,
    'terminal_id' => $terminal->id,
    'terminal_code' => $terminal->code,
    'terminal_credential' => $credential['token'],
    'assignment_uuid' => $assignment->uuid,
    'terminals' => array_map(fn ($manualTerminal, $index): array => ['id' => $manualTerminal->id, 'code' => $manualTerminal->code, 'credential' => $credentials[$index]['token'], 'assignment_uuid' => $assignments[$index]->uuid], $terminals, array_keys($terminals)),
    'schedule_version_id' => $meeting->current_schedule_version_id,
    'participant_version_id' => $meeting->current_participant_version_id,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

echo json_encode(['fixture' => '/tmp/flow10a_manual_fixture.json', 'meeting_id' => $meeting->id, 'terminal_id' => $terminal->id], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
