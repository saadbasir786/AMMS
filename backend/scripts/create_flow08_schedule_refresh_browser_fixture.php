<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

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

$existing = Meeting::where('meeting_number','FLOW08-SCHEDULE-REFRESH')->first();
if ($existing) { echo json_encode(['status'=>'EXISTS','meeting_id'=>$existing->id]), PHP_EOL; exit; }
$unit = fn(string $code) => OrganizationalUnit::where('code',$code)->firstOrFail();
$user = User::where('email','authority.office@example.test')->firstOrFail();
$authorityUser = User::where('email','high.authority@example.test')->firstOrFail();
$tech = Employee::where('email','tech.director@example.test')->firstOrFail();
$finance = Employee::where('email','finance.director@example.test')->firstOrFail();
$hr = Employee::where('email','hr.director@example.test')->firstOrFail();
$authority = OrganizationAuthority::where('authority_office_unit_id',$unit('AUTH-OFFICE')->id)->firstOrFail();
$request = MeetingRequest::create(['request_number'=>'FLOW08-SCHEDULE-REFRESH-REQUEST','flow_type'=>'HIGH_AUTHORITY_INITIATED','requesting_unit_id'=>$unit('AUTH-OFFICE')->id,'created_by'=>$user->id,'subject'=>'جلسهٔ آزمون بازنگری مبنای زمان‌بندی','purpose'=>'COORDINATION','description'=>'Fixture برای schedule changed mid-case FLOW-08.','status'=>'CONVERTED_TO_MEETING','coordinator_id'=>$user->id]);
$meeting = Meeting::create(['meeting_request_id'=>$request->id,'meeting_number'=>'FLOW08-SCHEDULE-REFRESH','title'=>$request->subject,'description'=>$request->description,'organizing_unit_id'=>$unit('AUTH-OFFICE')->id,'coordinator_id'=>$user->id,'chairperson_employee_id'=>$authorityUser->employee_id,'meeting_date'=>now()->addDays(35)->format('Y-m-d'),'start_time'=>'09:00','end_time'=>'10:00','location'=>'تالار مبنا','status'=>'SCHEDULED']);
foreach (['TECH','FIN','HR'] as $code) MeetingUnit::create(['meeting_id'=>$meeting->id,'organizational_unit_id'=>$unit($code)->id,'role'=>'PARTICIPATING']);
foreach ([$tech,$finance,$hr,$authorityUser->employee] as $employee) MeetingParticipant::create(['meeting_id'=>$meeting->id,'employee_id'=>$employee->id,'organizational_unit_id'=>$employee->organizational_unit_id,'role'=>$employee->id === $authorityUser->employee_id ? 'HIGH_AUTHORITY' : 'PARTICIPATING']);
MeetingAuthorityContext::create(['meeting_id'=>$meeting->id,'organization_authority_id'=>$authority->id,'authority_type'=>$authority->authority_type,'authority_position_id'=>$authority->authority_position_id,'authority_employee_id'=>$authorityUser->employee_id,'authority_office_unit_id'=>$authority->authority_office_unit_id]);
$schedule = app(ScheduleVersionService::class)->createNext($meeting,['meeting_date'=>$meeting->meeting_date->format('Y-m-d'),'start_time'=>$meeting->start_time,'end_time'=>$meeting->end_time,'location'=>$meeting->location],$user->id);
app(ScheduleVersionService::class)->applyToMeeting($meeting,$schedule);
app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext','participants.employee.unit']),$user->id);
echo json_encode(['status'=>'CREATED','meeting_id'=>$meeting->id,'meeting_number'=>$meeting->meeting_number,'schedule_version'=>1],JSON_UNESCAPED_UNICODE), PHP_EOL;
