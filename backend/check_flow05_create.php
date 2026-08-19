<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$actor = App\Models\User::where('email','tech.exec@example.test')->firstOrFail();
$tech = App\Models\OrganizationalUnit::where('code','TECH')->firstOrFail();
$finance = App\Models\OrganizationalUnit::where('code','FIN')->firstOrFail();
$employee = App\Models\Employee::where('employee_number','EMP-TECH-003')->firstOrFail();
$request = app(App\Actions\MeetingRequests\CreateMultiHighAuthorityMeetingRequest::class)($actor, [
    'requesting_unit_id'=>$tech->id,
    'participating_unit_ids'=>[$finance->id],
    'subject'=>'آزمون FLOW-05',
    'purpose'=>'DECISION',
    'description'=>'آزمون ایجاد جلسهٔ چندریاستی با مقام عالی.',
    'meeting_justification'=>'توجیه آزمایشی.',
    'requested_outcome'=>'نتیجهٔ آزمایشی.',
    'priority'=>'NORMAL',
    'participant_ids'=>[$employee->id],
]);
echo json_encode(['id'=>$request->id,'flow_type'=>$request->flow_type,'status'=>$request->status,'units'=>$request->units->map(fn($u)=>[$u->role,$u->organizational_unit_id])->all(),'authority_context'=>(bool)$request->authorityContext], JSON_UNESCAPED_UNICODE).PHP_EOL;
