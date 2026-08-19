<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Actions\Meetings\AcknowledgeMeetingRescheduleProposal;
use App\Actions\Meetings\ConfirmMeetingReschedule;
use App\Models\Meeting;
use App\Models\User;

$meeting = Meeting::with(['activeRescheduleCase.proposals','request','currentScheduleVersion'])->where('meeting_number','FLOW08-SCHEDULE-REFRESH')->firstOrFail();
$case = $meeting->activeRescheduleCase;
$proposal = $case->proposals()->where('status','ACTIVE')->firstOrFail();
$user = fn(string $email) => User::where('email',$email)->firstOrFail();
foreach (['tech.director@example.test','finance.director@example.test','hr.director@example.test'] as $email) {
    app(AcknowledgeMeetingRescheduleProposal::class)($user($email), $meeting, $case, $proposal);
}
$confirmed = app(ConfirmMeetingReschedule::class)($user('executive.secretary@example.test'), $meeting, $case);
echo json_encode(['meeting_id'=>$confirmed->id,'current_schedule_version_id'=>$confirmed->current_schedule_version_id,'version_count'=>$confirmed->scheduleVersions()->count(),'status'=>$confirmed->status],JSON_UNESCAPED_UNICODE), PHP_EOL;
