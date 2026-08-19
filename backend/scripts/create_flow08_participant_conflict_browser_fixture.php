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

if (Meeting::where('meeting_number','FLOW08-PARTICIPANT-CONFLICT-A')->exists()) {
    echo json_encode(['status'=>'EXISTS','meeting_a'=>Meeting::where('meeting_number','FLOW08-PARTICIPANT-CONFLICT-A')->value('id'),'meeting_b'=>Meeting::where('meeting_number','FLOW08-PARTICIPANT-CONFLICT-B')->value('id')]), PHP_EOL; exit;
}
$unit = fn(string $code) => OrganizationalUnit::where('code',$code)->firstOrFail();
$user = User::where('email','tech.exec@example.test')->firstOrFail();
$tech = Employee::where('email','tech.director@example.test')->firstOrFail();
$finance = Employee::where('email','finance.director@example.test')->firstOrFail();
$hr = Employee::where('email','hr.director@example.test')->firstOrFail();
$busy = Employee::where('email','employee.a@example.test')->firstOrFail();
$date = now()->addDays(30)->format('Y-m-d');
$make = function(string $number, string $subject, string $start, string $end, int $chairId, array $participants) use ($unit,$user,$date): Meeting {
    $request = MeetingRequest::create(['request_number'=>$number.'-REQUEST','flow_type'=>'MULTI_DIRECTORATE','requesting_unit_id'=>$unit('TECH')->id,'created_by'=>$user->id,'subject'=>$subject,'purpose'=>'COORDINATION','description'=>'Fixture مستقل برای participant conflict FLOW-08.','status'=>'CONVERTED_TO_MEETING','coordinator_id'=>$user->id]);
    $meeting = Meeting::create(['meeting_request_id'=>$request->id,'meeting_number'=>$number,'title'=>$subject,'description'=>$request->description,'organizing_unit_id'=>$unit('TECH')->id,'coordinator_id'=>$user->id,'chairperson_employee_id'=>$chairId,'meeting_date'=>$date,'start_time'=>$start,'end_time'=>$end,'location'=>'تالار conflict','status'=>'SCHEDULED']);
    foreach (['TECH','FIN','HR'] as $code) MeetingUnit::create(['meeting_id'=>$meeting->id,'organizational_unit_id'=>$unit($code)->id,'role'=>'PARTICIPATING']);
    foreach ($participants as $employee) MeetingParticipant::create(['meeting_id'=>$meeting->id,'employee_id'=>$employee->id,'organizational_unit_id'=>$employee->organizational_unit_id,'role'=>'PARTICIPATING']);
    $schedule = app(ScheduleVersionService::class)->createNext($meeting,['meeting_date'=>$date,'start_time'=>$start,'end_time'=>$end,'location'=>'تالار conflict'],$user->id);
    app(ScheduleVersionService::class)->applyToMeeting($meeting,$schedule);
    app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext','participants.employee.unit']),$user->id);
    return $meeting;
};
$a = $make('FLOW08-PARTICIPANT-CONFLICT-A','جلسهٔ هدف برای تعارض اشتراک‌کننده','10:00','11:00',$finance->id,[$tech,$finance,$hr]);
$b = $make('FLOW08-PARTICIPANT-CONFLICT-B','جلسهٔ مصروفیت کارمند X','10:30','11:30',$hr->id,[$busy,$hr]);
echo json_encode(['status'=>'CREATED','meeting_a'=>$a->id,'meeting_b'=>$b->id,'busy_employee'=>$busy->id,'date'=>$date,'target'=>'10:00-11:00','busy'=>'10:30-11:30'],JSON_UNESCAPED_UNICODE), PHP_EOL;
