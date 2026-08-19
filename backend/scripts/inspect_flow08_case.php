<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\MeetingParticipantChangeCase;
foreach (MeetingParticipantChangeCase::where('meeting_id', 11)->orderByDesc('id')->get() as $case) echo json_encode(['id'=>$case->id,'meeting_id'=>$case->meeting_id,'status'=>$case->status,'owner_type'=>$case->owner_type,'owner_unit_id'=>$case->owner_unit_id], JSON_UNESCAPED_UNICODE), PHP_EOL;
