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

$number = 'FLOW08-REMOVE-POSITIVE-V2';
if ($existing = Meeting::where('meeting_number',$number)->first()) { echo json_encode(['status'=>'EXISTS','meeting_id'=>$existing->id]), PHP_EOL; exit; }
$unit = fn(string $code) => OrganizationalUnit::where('code',$code)->firstOrFail();
$creator = User::where('email','tech.exec@example.test')->firstOrFail();
$tech = Employee::where('email','tech.director@example.test')->firstOrFail();
$extra = Employee::where('email','peer.coordinator@example.test')->firstOrFail();
$finance = Employee::where('email','finance.director@example.test')->firstOrFail();
$hr = Employee::where('email','hr.director@example.test')->firstOrFail();
$date = now()->addDays(56)->format('Y-m-d');
$request = MeetingRequest::create(['request_number'=>$number.'-REQUEST','flow_type'=>'MULTI_DIRECTORATE','requesting_unit_id'=>$unit('TECH')->id,'created_by'=>$creator->id,'subject'=>'جلسهٔ آزمون حذف معتبر participant','purpose'=>'COORDINATION','description'=>'Fixture برای REMOVE معتبر participant غیرالزامی FLOW-08.','status'=>'CONVERTED_TO_MEETING','coordinator_id'=>$creator->id]);
$meeting = Meeting::create(['meeting_request_id'=>$request->id,'meeting_number'=>$number,'title'=>$request->subject,'description'=>$request->description,'organizing_unit_id'=>$unit('TECH')->id,'coordinator_id'=>$creator->id,'chairperson_employee_id'=>$finance->id,'meeting_date'=>$date,'start_time'=>'11:00','end_time'=>'12:00','location'=>'تالار حذف معتبر','status'=>'SCHEDULED']);
foreach (['TECH','FIN','HR'] as $code) MeetingUnit::create(['meeting_id'=>$meeting->id,'organizational_unit_id'=>$unit($code)->id,'role'=>'PARTICIPATING']);
foreach ([$tech,$extra,$finance,$hr] as $employee) MeetingParticipant::create(['meeting_id'=>$meeting->id,'employee_id'=>$employee->id,'organizational_unit_id'=>$employee->organizational_unit_id,'role'=>$employee->id === $extra->id ? 'OPTIONAL' : 'PARTICIPATING']);
$schedule = app(ScheduleVersionService::class)->createNext($meeting,['meeting_date'=>$date,'start_time'=>'11:00','end_time'=>'12:00','location'=>'تالار حذف معتبر'],$creator->id);
app(ScheduleVersionService::class)->applyToMeeting($meeting,$schedule);
app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request','participants.employee.unit']),$creator->id);
echo json_encode(['status'=>'CREATED','meeting_id'=>$meeting->id,'meeting_number'=>$number,'removed_employee'=>$extra->id,'removed_name'=>'هماهنگ‌کننده peer'],JSON_UNESCAPED_UNICODE), PHP_EOL;
