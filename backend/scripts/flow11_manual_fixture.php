<?php

declare(strict_types=1);

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

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$coordinator = User::query()->where('email', 'tech.coordinator@example.test')->firstOrFail();
$director = User::query()->where('email', 'tech.director@example.test')->firstOrFail();
$unit = OrganizationalUnit::query()->where('code', 'TECH')->firstOrFail();
$employee = Employee::query()->where('email', 'tech.director@example.test')->firstOrFail();
$request = MeetingRequest::create(['request_number' => 'FLOW11-MANUAL-'.Str::random(8), 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $coordinator->id, 'subject' => 'جلسهٔ آزمایشی FLOW-11', 'purpose' => 'DECISION', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $coordinator->id]);
$meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => 'FLOW11-MANUAL-M-'.Str::random(6), 'title' => $request->subject, 'organizing_unit_id' => $unit->id, 'coordinator_id' => $coordinator->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->addDays(10)->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'اتاق جلسهٔ آزمایشی', 'status' => 'SCHEDULED']);
MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
$schedule = app(ScheduleVersionService::class);
$version = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => $meeting->location], $coordinator->id);
$schedule->applyToMeeting($meeting, $version);
app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $coordinator->id);
echo json_encode(['meeting_id' => $meeting->id, 'unit_id' => $unit->id, 'coordinator_email' => $coordinator->email, 'director_email' => $director->email], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT).PHP_EOL;
