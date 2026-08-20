<?php

declare(strict_types=1);

use App\Actions\Flow12\CreateMeetingActionItem;
use App\Actions\Flow12\InitializeMeetingDecisionFollowUp;
use App\Actions\Flow12\RequestActionItemDueDateExtension;
use App\Actions\Flow12\ReviewActionItemDueDateExtension;
use App\Actions\Flow12\ChangeActionItemAssignment;
use App\Actions\Flow12\RecordActionItemProgress;
use App\Actions\Flow12\CancelMeetingActionItem;
use App\Actions\Flow12\VerifyActionItemCompletion;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingExecutionSession;
use App\Models\MeetingMinuteVersion;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\MeetingMinutes;
use App\Models\MeetingParticipant;
use App\Models\MeetingRequest;
use App\Models\MeetingUnit;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\ParticipantVersionService;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

putenv('DB_CONNECTION=mysql');
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default' => 'mysql']);

$reconnect = static function (): void { DB::purge('mysql'); DB::reconnect('mysql'); };
$runTwo = static function (callable $worker) use ($reconnect): array {
    $children = [];
    for ($i = 0; $i < 2; $i++) {
        [$read, $write] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();
        if ($pid < 0) throw new RuntimeException('fork failed');
        if ($pid === 0) {
            fclose($read);
            try { $reconnect(); $out = ['ok' => true, 'result' => $worker($i)]; }
            catch (Throwable $e) { $out = ['ok' => false, 'class' => $e::class, 'message' => $e->getMessage()]; }
            fwrite($write, json_encode($out, JSON_UNESCAPED_UNICODE)); fclose($write); exit(0);
        }
        fclose($write); $children[] = [$pid, $read];
    }
    $results = [];
    foreach ($children as [$pid, $read]) { $results[] = json_decode(stream_get_contents($read), true, 512, JSON_THROW_ON_ERROR); fclose($read); pcntl_waitpid($pid, $status); }
    return $results;
};

$owner = User::where('email', 'tech.coordinator@example.test')->firstOrFail();
$employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
$unit = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$employee = Employee::where('email', 'tech.director@example.test')->firstOrFail();
$tag = 'F12-RACE-'.Str::upper(Str::random(7));
$request = MeetingRequest::create(['request_number' => $tag.'-REQ', 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $owner->id, 'subject' => 'FLOW-12 MariaDB race', 'purpose' => 'CONCURRENCY', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
$meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => $tag.'-MEETING', 'title' => 'FLOW-12 MariaDB race', 'organizing_unit_id' => $unit->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->subDay()->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW12-RACE', 'status' => 'COMPLETED']);
MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
$schedule = app(ScheduleVersionService::class);
$scheduleVersion = $schedule->createNext($meeting, ['meeting_date' => $meeting->meeting_date->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW12-RACE'], $owner->id);
$schedule->applyToMeeting($meeting, $scheduleVersion);
$participantVersion = app(ParticipantVersionService::class)->ensureInitialVersion($meeting->fresh(['request.authorityContext', 'participants.employee.unit']), $owner->id);
$session = MeetingExecutionSession::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'status' => 'ENDED', 'started_at' => now()->subHour(), 'started_by_user_id' => $owner->id, 'ended_at' => now()->subMinutes(30), 'ended_by_user_id' => $owner->id]);
$minutes = MeetingMinutes::create(['uuid' => (string) Str::uuid(), 'meeting_id' => $meeting->id, 'status' => MeetingMinutes::FINALIZED, 'created_by_user_id' => $owner->id, 'finalized_at' => now()->subMinutes(10), 'finalized_by_user_id' => $owner->id]);
$version = MeetingMinuteVersion::create(['uuid' => (string) Str::uuid(), 'meeting_minutes_id' => $minutes->id, 'version_number' => 1, 'execution_session_id' => $session->id, 'schedule_version_id' => $scheduleVersion->id, 'participant_version_id' => $participantVersion->id, 'summary' => 'FLOW-12 race', 'prepared_by_user_id' => $owner->id, 'submitted_at' => now()->subMinutes(20)]);
$decision = MeetingMinuteVersionDecision::create(['uuid' => (string) Str::uuid(), 'meeting_minute_version_id' => $version->id, 'sequence_number' => 1, 'decision_number' => 'RACE-DEC-001', 'decision_text' => 'تصمیم آزمون همزمانی.', 'responsible_unit_id' => $unit->id, 'responsible_employee_id' => $employee->id, 'target_date' => now()->addDays(10)->format('Y-m-d')]);
$minutes->update(['current_version_id' => $version->id]);
$meeting = $meeting->fresh(['minutes']);

$initResults = $runTwo(static function () use ($meeting, $owner): string {
    app(InitializeMeetingDecisionFollowUp::class)->execute(Meeting::with('minutes')->findOrFail($meeting->id), User::findOrFail($owner->id));
    return 'FOLLOWUP_INITIALIZED';
});
$followup = MeetingDecisionFollowUp::where('meeting_id', $meeting->id)->firstOrFail();
app(\App\Actions\Flow12\ClassifyMeetingDecision::class)->execute($followup->fresh(['meeting']), $owner, 'ACTION_REQUIRED');
$item = app(CreateMeetingActionItem::class)->execute($followup->fresh(['meeting', 'decision']), $owner, ['title' => 'اقدام آزمون همزمانی', 'responsible_unit_id' => $unit->id, 'assigned_employee_id' => $employee->id, 'due_date' => now()->addDays(3)->format('Y-m-d')]);
$sequenceResults = $runTwo(static function (int $worker) use ($followup, $owner, $unit): string {
    app(CreateMeetingActionItem::class)->execute($followup->fresh(['meeting', 'decision']), User::findOrFail($owner->id), ['title' => 'اقدام شماره‌گذاری '.$worker, 'responsible_unit_id' => $unit->id, 'assigned_employee_id' => null, 'due_date' => now()->addDays(4)->format('Y-m-d')]);
    return 'ACTION_ITEM_CREATED';
});
$item->update(['status' => MeetingActionItem::IN_PROGRESS]);
$extensionResults = $runTwo(static function () use ($item, $employeeUser): string {
    app(RequestActionItemDueDateExtension::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($item->id), User::findOrFail($employeeUser->id), now()->addDays(12)->format('Y-m-d'), 'درخواست رقابتی آزمون');
    return 'EXTENSION_REQUESTED';
});
$extension = DB::table('meeting_action_item_extension_requests')->where('action_item_id', $item->id)->first();
$extensionReviewResults = $runTwo(static function (int $worker) use ($extension, $owner): string {
    app(ReviewActionItemDueDateExtension::class)->execute(\App\Models\MeetingActionItemExtensionRequest::findOrFail($extension->id), User::findOrFail($owner->id), $worker === 0, $worker === 0 ? null : 'رقابت بررسی');
    return $worker === 0 ? 'EXTENSION_APPROVED' : 'EXTENSION_REJECTED';
});
$progressCancelItem = app(CreateMeetingActionItem::class)->execute($followup->fresh(['meeting', 'decision']), $owner, ['title' => 'اقدام رقابت پیشرفت و لغو', 'responsible_unit_id' => $unit->id, 'assigned_employee_id' => null, 'due_date' => now()->addDays(4)->format('Y-m-d')]);
$progressCancelItem->update(['status' => MeetingActionItem::IN_PROGRESS]);
$progressCancelResults = $runTwo(static function (int $worker) use ($progressCancelItem, $employeeUser, $owner): string {
    if ($worker === 0) {
        app(RecordActionItemProgress::class)->execute(MeetingActionItem::findOrFail($progressCancelItem->id), User::findOrFail($employeeUser->id), 70, 'پیشرفت رقابتی');
        return 'PROGRESS_RECORDED';
    }
    app(CancelMeetingActionItem::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($progressCancelItem->id), User::findOrFail($owner->id), 'لغو رقابتی');
    return 'ACTION_ITEM_CANCELLED';
});
$terminalRaceItem = app(CreateMeetingActionItem::class)->execute($followup->fresh(['meeting', 'decision']), $owner, ['title' => 'اقدام رقابت تأیید و تخصیص', 'responsible_unit_id' => $unit->id, 'assigned_employee_id' => $employee->id, 'due_date' => now()->addDays(4)->format('Y-m-d')]);
$terminalRaceItem->update(['status' => MeetingActionItem::PENDING_VERIFICATION, 'progress_percent' => 100, 'submitted_by_user_id' => $employeeUser->id, 'submitted_for_verification_at' => now()]);
$completionReassignmentResults = $runTwo(static function (int $worker) use ($terminalRaceItem, $owner, $unit, $employee): string {
    if ($worker === 0) {
        app(VerifyActionItemCompletion::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($terminalRaceItem->id), User::findOrFail($owner->id));
        return 'ACTION_ITEM_COMPLETED';
    }
    app(ChangeActionItemAssignment::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($terminalRaceItem->id), User::findOrFail($owner->id), $unit->id, $employee->id, 'تخصیص رقابتی');
    return 'ACTION_ITEM_REASSIGNED';
});
$item->update(['status' => MeetingActionItem::PENDING_VERIFICATION, 'progress_percent' => 100, 'submitted_by_user_id' => $employeeUser->id, 'submitted_for_verification_at' => now()]);
$verifyResults = $runTwo(static function () use ($item, $owner): string {
    app(VerifyActionItemCompletion::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($item->id), User::findOrFail($owner->id));
    return 'ACTION_ITEM_COMPLETED';
});

$out = [
    'probe' => 'FLOW-12 MariaDB action-level race probe',
    'fixture_tag' => $tag,
    'concurrent_followup_initialization' => $initResults,
    'followup_root_rows' => MeetingDecisionFollowUp::where('meeting_id', $meeting->id)->count(),
    'concurrent_action_item_sequence_creation' => $sequenceResults,
    'action_item_rows_after_sequence_race' => MeetingActionItem::where('decision_followup_id', $followup->id)->count(),
    'concurrent_extension_requests' => $extensionResults,
    'concurrent_extension_review' => $extensionReviewResults,
    'pending_extension_rows_after_review' => DB::table('meeting_action_item_extension_requests')->where('action_item_id', $item->id)->where('status', 'PENDING')->count(),
    'extension_terminal_rows' => DB::table('meeting_action_item_extension_requests')->where('action_item_id', $item->id)->whereIn('status', ['APPROVED', 'REJECTED'])->count(),
    'concurrent_progress_cancel' => $progressCancelResults,
    'progress_cancel_terminal_rows' => MeetingActionItem::whereKey($progressCancelItem->id)->whereIn('status', [MeetingActionItem::CANCELLED, MeetingActionItem::IN_PROGRESS])->count(),
    'concurrent_completion_reassignment' => $completionReassignmentResults,
    'completion_reassignment_completed_rows' => MeetingActionItem::whereKey($terminalRaceItem->id)->where('status', MeetingActionItem::COMPLETED)->count(),
    'concurrent_completion_verification' => $verifyResults,
    'completed_action_item_rows' => MeetingActionItem::whereKey($item->id)->where('status', MeetingActionItem::COMPLETED)->count(),
    'completed_audit_rows' => DB::table('meeting_actions')->where('meeting_id', $meeting->id)->where('action', 'ACTION_ITEM_COMPLETED')->count(),
];
$out['pass'] = $out['followup_root_rows'] === 1
    && $out['action_item_rows_after_sequence_race'] === 5
    && $out['pending_extension_rows_after_review'] === 0
    && $out['extension_terminal_rows'] === 1
    && $out['progress_cancel_terminal_rows'] === 1
    && $out['completion_reassignment_completed_rows'] === 1
    && $out['completed_action_item_rows'] === 1
    && $out['completed_audit_rows'] === 2;
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
