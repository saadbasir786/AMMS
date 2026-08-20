<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Str;

$owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
$director = User::where('email', 'tech.director@example.test')->firstOrFail();
$unit = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$employee = Employee::findOrFail($director->employee_id);
$request = MeetingRequest::create(['request_number' => 'FLOW13-HTTP-'.uniqid(), 'flow_type' => 'INTERNAL_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $owner->id, 'subject' => 'جلسهٔ استثنایی HTTP FLOW-13', 'purpose' => 'COORDINATION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
$meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW13-HTTP-M-'.uniqid(), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->addDays(9)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'تالار FLOW-13 HTTP', 'status' => 'SCHEDULED']);
MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'REQUESTING']);
MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
$schedule = app(ScheduleVersionService::class);
$version = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => $meeting->start_time, 'end_time' => $meeting->end_time, 'location' => $meeting->location], $owner->id);
$schedule->applyToMeeting($meeting, $version);
$participants = app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);
echo json_encode(['meeting_id' => $meeting->id, 'meeting_request_id' => $request->id, 'request_status' => $request->status, 'meeting_number' => $meeting->meeting_number, 'baseline_schedule_version_id' => $version->id, 'owner_email' => $owner->email, 'director_email' => $director->email, 'meeting_status' => $meeting->fresh()->status, 'participant_version_id' => $participants->id], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
