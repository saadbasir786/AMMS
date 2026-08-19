<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;

$existing = Meeting::where('meeting_number', 'FLOW08-PEER-BROWSER')->first();
if ($existing) { echo json_encode(['meeting_id'=>$existing->id,'status'=>'EXISTS']), PHP_EOL; exit; }
$unit = fn (string $code) => OrganizationalUnit::where('code', $code)->firstOrFail();
$user = User::where('email', 'tech.exec@example.test')->firstOrFail();
$employees = [
    Employee::where('email', 'tech.director@example.test')->firstOrFail(),
    Employee::where('email', 'finance.director@example.test')->firstOrFail(),
    Employee::where('email', 'hr.director@example.test')->firstOrFail(),
];
$request = MeetingRequest::create([
    'request_number' => 'FLOW08-PEER-BROWSER-REQUEST', 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit('TECH')->id,
    'created_by' => $user->id, 'subject' => 'جلسهٔ آزمایشی تغییر اشتراک‌کنندگان ریاست‌ها', 'purpose' => 'COORDINATION',
    'description' => 'Fixture مستقل برای browser gate FLOW-08.', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $user->id,
]);
$meeting = Meeting::create([
    'meeting_request_id' => $request->id, 'meeting_number' => 'FLOW08-PEER-BROWSER', 'title' => $request->subject,
    'description' => $request->description, 'organizing_unit_id' => $unit('TECH')->id, 'coordinator_id' => $user->id,
    'chairperson_employee_id' => $employees[0]->id, 'meeting_date' => now()->addDays(25)->format('Y-m-d'), 'start_time' => '11:00',
    'end_time' => '12:00', 'location' => 'تالار آزمایشی ریاست‌ها', 'status' => 'SCHEDULED',
]);
foreach (['TECH','FIN','HR'] as $code) MeetingUnit::create(['meeting_id'=>$meeting->id,'organizational_unit_id'=>$unit($code)->id,'role'=>'PARTICIPATING']);
foreach ($employees as $employee) MeetingParticipant::create(['meeting_id'=>$meeting->id,'employee_id'=>$employee->id,'organizational_unit_id'=>$employee->organizational_unit_id,'role'=>'PARTICIPATING']);
$schedule = app(ScheduleVersionService::class)->createNext($meeting, ['meeting_date'=>$meeting->meeting_date->format('Y-m-d'),'start_time'=>$meeting->start_time,'end_time'=>$meeting->end_time,'location'=>$meeting->location], $user->id);
app(ScheduleVersionService::class)->applyToMeeting($meeting, $schedule);
app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext','participants.employee.unit']), $user->id);
echo json_encode(['meeting_id'=>$meeting->id,'meeting_number'=>$meeting->meeting_number,'status'=>'CREATED'], JSON_UNESCAPED_UNICODE), PHP_EOL;
