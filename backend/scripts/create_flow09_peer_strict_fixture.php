<?php
declare(strict_types=1);

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

$number = 'FLOW09-STRICT-PEER-20';
if ($existing = Meeting::where('meeting_number', $number)->first()) { echo json_encode(['status'=>'EXISTS','meeting_id'=>$existing->id], JSON_UNESCAPED_UNICODE).PHP_EOL; exit; }
$unit = fn (string $code) => OrganizationalUnit::where('code', $code)->firstOrFail();
$coordinator = User::where('email', 'tech.exec@example.test')->firstOrFail();
$tech = Employee::where('email', 'tech.exec@example.test')->firstOrFail();
$finance = Employee::where('email', 'finance.director@example.test')->firstOrFail();
$hr = Employee::where('email', 'hr.director@example.test')->firstOrFail();
$date = now()->addDays(75)->format('Y-m-d');
$request = MeetingRequest::create(['request_number'=>$number.'-REQUEST','flow_type'=>'MULTI_DIRECTORATE','requesting_unit_id'=>$unit('TECH-EXEC')->id,'created_by'=>$coordinator->id,'subject'=>'جلسهٔ peer برای gateهای سختگیر FLOW-09','purpose'=>'COORDINATION','description'=>'Fixture با coordinator و organizing unit دقیق TECH-EXEC.','status'=>'CONVERTED_TO_MEETING','coordinator_id'=>$coordinator->id]);
$meeting = Meeting::create(['meeting_request_id'=>$request->id,'meeting_number'=>$number,'title'=>$request->subject,'description'=>$request->description,'organizing_unit_id'=>$unit('TECH-EXEC')->id,'coordinator_id'=>$coordinator->id,'chairperson_employee_id'=>$finance->id,'meeting_date'=>$date,'start_time'=>'14:00','end_time'=>'15:00','location'=>'تالار peer FLOW-09','status'=>'SCHEDULED']);
foreach ([['TECH-EXEC','REQUESTING'],['FIN','PARTICIPATING'],['HR','PARTICIPATING']] as [$code,$role]) MeetingUnit::create(['meeting_id'=>$meeting->id,'organizational_unit_id'=>$unit($code)->id,'role'=>$role]);
foreach ([$tech,$finance,$hr] as $employee) MeetingParticipant::create(['meeting_id'=>$meeting->id,'employee_id'=>$employee->id,'organizational_unit_id'=>$employee->organizational_unit_id,'role'=>'PARTICIPATING']);
$schedule = app(ScheduleVersionService::class)->createNext($meeting,['meeting_date'=>$date,'start_time'=>'14:00','end_time'=>'15:00','location'=>'تالار peer FLOW-09'],$coordinator->id);
app(ScheduleVersionService::class)->applyToMeeting($meeting,$schedule);
app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request','participants.employee.unit']),$coordinator->id);
echo json_encode(['status'=>'CREATED','meeting_id'=>$meeting->id,'meeting_number'=>$number,'meeting_date'=>$date,'coordinator_id'=>$coordinator->id,'organizing_unit_id'=>$meeting->organizing_unit_id], JSON_UNESCAPED_UNICODE).PHP_EOL;
