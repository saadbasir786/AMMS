<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Employee;
use App\Models\Meeting;
$meeting = Meeting::where('meeting_number', 'FLOW08-PEER-BROWSER')->firstOrFail();
$meeting->update(['chairperson_employee_id' => Employee::where('email','finance.director@example.test')->firstOrFail()->id]);
echo json_encode(['meeting_id'=>$meeting->id,'chairperson_employee_id'=>$meeting->fresh()->chairperson_employee_id]), PHP_EOL;
