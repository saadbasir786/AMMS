<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;

$meeting = Meeting::where('meeting_number','FLOW08-REMOVE-POSITIVE')->firstOrFail();
$creator = App\Models\User::where('email','tech.exec@example.test')->firstOrFail();
$emails = ['tech.director@example.test','employee.a@example.test','finance.director@example.test','hr.director@example.test'];
$extra = Employee::where('email','employee.a@example.test')->firstOrFail();
foreach ($emails as $email) {
    $employee = Employee::where('email',$email)->firstOrFail();
    MeetingParticipant::firstOrCreate(
        ['meeting_id'=>$meeting->id,'employee_id'=>$employee->id],
        ['organizational_unit_id'=>$employee->organizational_unit_id,'role'=>$employee->id === $extra->id ? 'OPTIONAL' : 'PARTICIPATING']
    );
}
if (!$meeting->scheduleVersions()->exists()) {
    $schedule = app(ScheduleVersionService::class)->createNext($meeting,['meeting_date'=>$meeting->meeting_date,'start_time'=>$meeting->start_time,'end_time'=>$meeting->end_time,'location'=>$meeting->location],$creator->id);
    app(ScheduleVersionService::class)->applyToMeeting($meeting,$schedule);
}
$meeting->load(['request','participants.employee.unit']);
if (!$meeting->participantVersions()->exists()) app(ParticipantVersionService::class)->ensureInitialVersion($meeting,$creator->id);
echo json_encode(['status'=>'REPAIRED','meeting_id'=>$meeting->id,'participants'=>$meeting->participants->map(fn($p)=>['employee_id'=>$p->employee_id,'name'=>$p->employee->name,'role'=>$p->role])->values()],JSON_UNESCAPED_UNICODE), PHP_EOL;
