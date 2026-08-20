<?php

declare(strict_types=1);

use App\Actions\Flow12\CancelMeetingActionItem;
use App\Actions\Flow12\ChangeActionItemAssignment;
use App\Actions\Flow12\CreateMeetingActionItem;
use App\Actions\Flow12\InitializeMeetingDecisionFollowUp;
use App\Actions\Flow12\RecordActionItemProgress;
use App\Actions\Flow12\RequestActionItemDueDateExtension;
use App\Actions\Flow12\ReviewActionItemDueDateExtension;
use App\Actions\Flow12\SubmitActionItemCompletion;
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
$runOrdered = static function (callable $first, callable $second) use ($reconnect): array {
    $runOne = static function (callable $worker) use ($reconnect): array {
        [$read, $write] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();
        if ($pid < 0) throw new RuntimeException('fork failed');
        if ($pid === 0) {
            fclose($read);
            try { $reconnect(); $out = ['ok' => true, 'result' => $worker()]; }
            catch (Throwable $e) { $out = ['ok' => false, 'class' => $e::class, 'message' => $e->getMessage()]; }
            fwrite($write, json_encode($out, JSON_UNESCAPED_UNICODE)); fclose($write); exit(0);
        }
        fclose($write);
        $out = json_decode(stream_get_contents($read), true, 512, JSON_THROW_ON_ERROR);
        fclose($read); pcntl_waitpid($pid, $status);
        return $out;
    };
    return [$runOne($first), $runOne($second)];
};
$exceptionCode = static fn (array $result): ?string => $result['ok'] ? null : (string) ($result['message'] ?? null);
$actionIdentities = static function (array $results): array {
    return array_values(array_map(static fn (array $result): array => [
        'id' => $result['result']['id'] ?? null,
        'sequence_number' => $result['result']['sequence_number'] ?? null,
        'action_number' => $result['result']['action_number'] ?? null,
    ], array_filter($results, static fn (array $result): bool => $result['ok'] && is_array($result['result']))));
};

$owner = User::where('email', 'tech.exec@example.test')->firstOrFail();
$employeeUser = User::where('email', 'tech.director@example.test')->firstOrFail();
$unit = OrganizationalUnit::where('code', 'TECH')->firstOrFail();
$employee = Employee::where('email', 'tech.director@example.test')->firstOrFail();
$alternateEmployee = Employee::where('email', 'employee.a@example.test')->firstOrFail();
$alternateUnit = OrganizationalUnit::where('code', 'TECH-SW')->firstOrFail();
$tag = 'F12-RACE-'.Str::upper(Str::random(7));
$request = MeetingRequest::create(['request_number' => $tag.'-REQ', 'flow_type' => 'MULTI_DIRECTORATE', 'requesting_unit_id' => $unit->id, 'created_by' => $owner->id, 'subject' => 'FLOW-12 MariaDB race', 'purpose' => 'CONCURRENCY', 'status' => 'CONVERTED_TO_MEETING', 'coordinator_id' => $owner->id]);
$meeting = Meeting::create(['meeting_request_id' => $request->id, 'meeting_number' => $tag.'-MEETING', 'title' => 'FLOW-12 MariaDB race', 'organizing_unit_id' => $unit->id, 'coordinator_id' => $owner->id, 'chairperson_employee_id' => $employee->id, 'meeting_date' => now()->subDay()->format('Y-m-d'), 'start_time' => '10:00', 'end_time' => '11:00', 'location' => 'FLOW12-RACE', 'status' => 'COMPLETED']);
MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $unit->id, 'role' => 'PARTICIPATING']);
MeetingUnit::create(['meeting_id' => $meeting->id, 'organizational_unit_id' => $alternateUnit->id, 'role' => 'PARTICIPATING']);
MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $employee->id, 'organizational_unit_id' => $employee->organizational_unit_id, 'role' => 'PARTICIPATING']);
MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $alternateEmployee->id, 'organizational_unit_id' => $alternateEmployee->organizational_unit_id, 'role' => 'PARTICIPATING']);
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
$sequenceResults = $runTwo(static function (int $worker) use ($followup, $owner, $unit): array {
    $created = app(CreateMeetingActionItem::class)->execute($followup->fresh(['meeting', 'decision']), User::findOrFail($owner->id), ['title' => 'اقدام شماره‌گذاری '.$worker, 'responsible_unit_id' => $unit->id, 'assigned_employee_id' => null, 'due_date' => now()->addDays(4)->format('Y-m-d')]);
    return ['id' => $created->id, 'sequence_number' => $created->sequence_number, 'action_number' => $created->action_number];
});
$item->update(['status' => MeetingActionItem::IN_PROGRESS]);
$extensionResults = $runTwo(static function () use ($item, $employeeUser): string {
    app(RequestActionItemDueDateExtension::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($item->id), User::findOrFail($employeeUser->id), now()->addDays(12)->format('Y-m-d'), 'درخواست رقابتی آزمون');
    return 'EXTENSION_REQUESTED';
});
$extension = DB::table('meeting_action_item_extension_requests')->where('action_item_id', $item->id)->where('status', 'PENDING')->first();
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

$submissionA = app(CreateMeetingActionItem::class)->execute($followup->fresh(['meeting', 'decision']), $owner, ['title' => 'ارسال و تخصیص؛ تخصیص نخست', 'responsible_unit_id' => $unit->id, 'assigned_employee_id' => $employee->id, 'due_date' => now()->addDays(4)->format('Y-m-d')]);
$submissionA->update(['status' => MeetingActionItem::IN_PROGRESS, 'progress_percent' => 100]);
$submissionReassignmentA = $runOrdered(
    static function () use ($submissionA, $owner, $alternateUnit, $alternateEmployee): string {
        app(ChangeActionItemAssignment::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($submissionA->id), User::findOrFail($owner->id), $alternateUnit->id, $alternateEmployee->id, 'تخصیص نخست در آزمون ترتیبی');
        return 'ACTION_ITEM_REASSIGNED';
    },
    static function () use ($submissionA, $employeeUser): string {
        app(SubmitActionItemCompletion::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($submissionA->id), User::findOrFail($employeeUser->id), 'ارسال پس از تخصیص');
        return 'COMPLETION_SUBMITTED';
    },
);
$submissionAFinal = MeetingActionItem::findOrFail($submissionA->id);

$submissionB = app(CreateMeetingActionItem::class)->execute($followup->fresh(['meeting', 'decision']), $owner, ['title' => 'ارسال و تخصیص؛ ارسال نخست', 'responsible_unit_id' => $unit->id, 'assigned_employee_id' => $employee->id, 'due_date' => now()->addDays(4)->format('Y-m-d')]);
$submissionB->update(['status' => MeetingActionItem::IN_PROGRESS, 'progress_percent' => 100]);
$submissionReassignmentB = $runOrdered(
    static function () use ($submissionB, $employeeUser): string {
        app(SubmitActionItemCompletion::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($submissionB->id), User::findOrFail($employeeUser->id), 'ارسال نخست در آزمون ترتیبی');
        return 'COMPLETION_SUBMITTED';
    },
    static function () use ($submissionB, $owner, $alternateUnit, $alternateEmployee): string {
        app(ChangeActionItemAssignment::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($submissionB->id), User::findOrFail($owner->id), $alternateUnit->id, $alternateEmployee->id, 'تخصیص پس از ارسال');
        return 'ACTION_ITEM_REASSIGNED';
    },
);
$submissionBFinal = MeetingActionItem::findOrFail($submissionB->id);

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

$oldDueA = now()->addDays(5)->format('Y-m-d');
$newDueA = now()->addDays(20)->format('Y-m-d');
$extensionAItem = app(CreateMeetingActionItem::class)->execute($followup->fresh(['meeting', 'decision']), $owner, ['title' => 'تمدید و تأیید؛ تمدید نخست', 'responsible_unit_id' => $unit->id, 'assigned_employee_id' => $employee->id, 'due_date' => $oldDueA]);
$extensionAItem->update(['status' => MeetingActionItem::IN_PROGRESS]);
$extensionARequest = app(RequestActionItemDueDateExtension::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($extensionAItem->id), $employeeUser, $newDueA, 'تمدید ترتیبی نخست');
$extensionAItem->update(['status' => MeetingActionItem::PENDING_VERIFICATION, 'progress_percent' => 100, 'submitted_by_user_id' => $employeeUser->id, 'submitted_for_verification_at' => now()]);
$extensionOrderingA = $runOrdered(
    static function () use ($extensionARequest, $owner): string {
        app(ReviewActionItemDueDateExtension::class)->execute(\App\Models\MeetingActionItemExtensionRequest::findOrFail($extensionARequest->id), User::findOrFail($owner->id), true);
        return 'EXTENSION_APPROVED';
    },
    static function () use ($extensionAItem, $owner): string {
        app(VerifyActionItemCompletion::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($extensionAItem->id), User::findOrFail($owner->id));
        return 'ACTION_ITEM_COMPLETED';
    },
);
$extensionAFinal = MeetingActionItem::findOrFail($extensionAItem->id);
$extensionAFinalRequest = \App\Models\MeetingActionItemExtensionRequest::findOrFail($extensionARequest->id);

$oldDueB = now()->addDays(6)->format('Y-m-d');
$newDueB = now()->addDays(21)->format('Y-m-d');
$extensionBItem = app(CreateMeetingActionItem::class)->execute($followup->fresh(['meeting', 'decision']), $owner, ['title' => 'تمدید و تأیید؛ تکمیل نخست', 'responsible_unit_id' => $unit->id, 'assigned_employee_id' => $employee->id, 'due_date' => $oldDueB]);
$extensionBItem->update(['status' => MeetingActionItem::IN_PROGRESS]);
$extensionBRequest = app(RequestActionItemDueDateExtension::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($extensionBItem->id), $employeeUser, $newDueB, 'تمدید ترتیبی دوم');
$extensionBItem->update(['status' => MeetingActionItem::PENDING_VERIFICATION, 'progress_percent' => 100, 'submitted_by_user_id' => $employeeUser->id, 'submitted_for_verification_at' => now()]);
$extensionOrderingB = $runOrdered(
    static function () use ($extensionBItem, $owner): string {
        app(VerifyActionItemCompletion::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($extensionBItem->id), User::findOrFail($owner->id));
        return 'ACTION_ITEM_COMPLETED';
    },
    static function () use ($extensionBRequest, $owner): string {
        app(ReviewActionItemDueDateExtension::class)->execute(\App\Models\MeetingActionItemExtensionRequest::findOrFail($extensionBRequest->id), User::findOrFail($owner->id), true);
        return 'EXTENSION_APPROVED';
    },
);
$extensionBFinal = MeetingActionItem::findOrFail($extensionBItem->id);
$extensionBFinalRequest = \App\Models\MeetingActionItemExtensionRequest::findOrFail($extensionBRequest->id);

$item->update(['status' => MeetingActionItem::PENDING_VERIFICATION, 'progress_percent' => 100, 'submitted_by_user_id' => $employeeUser->id, 'submitted_for_verification_at' => now()]);
$verifyResults = $runTwo(static function () use ($item, $owner): string {
    app(VerifyActionItemCompletion::class)->execute(MeetingActionItem::with('followup.meeting')->findOrFail($item->id), User::findOrFail($owner->id));
    return 'ACTION_ITEM_COMPLETED';
});

$auditEventsForItem = static function (int $itemId) use ($meeting): array {
    return DB::table('meeting_actions')->where('meeting_id', $meeting->id)->get(['action', 'metadata'])->filter(static function ($row) use ($itemId): bool {
        $metadata = is_array($row->metadata) ? $row->metadata : json_decode((string) $row->metadata, true);
        return (int) ($metadata['action_item_id'] ?? 0) === $itemId;
    })->pluck('action')->values()->all();
};
$pendingTerminalExtensions = DB::table('meeting_action_items as items')
    ->join('meeting_action_item_extension_requests as extensions', 'extensions.action_item_id', '=', 'items.id')
    ->where('items.decision_followup_id', $followup->id)
    ->whereIn('items.status', [MeetingActionItem::COMPLETED, MeetingActionItem::CANCELLED])
    ->where('extensions.status', 'PENDING')
    ->count();
$sequenceIdentities = $actionIdentities($sequenceResults);
$raceGroups = [
    ['name' => 'concurrent_followup_initialization', 'results' => $initResults],
    ['name' => 'concurrent_action_item_sequence_creation', 'results' => $sequenceResults],
    ['name' => 'concurrent_extension_requests', 'results' => $extensionResults],
    ['name' => 'concurrent_extension_review', 'results' => $extensionReviewResults],
    ['name' => 'concurrent_progress_cancel', 'results' => $progressCancelResults],
    ['name' => 'submission_vs_reassignment_ordering_a_reassignment_first', 'results' => $submissionReassignmentA],
    ['name' => 'submission_vs_reassignment_ordering_b_submission_first', 'results' => $submissionReassignmentB],
    ['name' => 'concurrent_completion_verification_reassignment', 'results' => $completionReassignmentResults],
    ['name' => 'extension_vs_completion_ordering_a_extension_first', 'results' => $extensionOrderingA],
    ['name' => 'extension_vs_completion_ordering_b_completion_first', 'results' => $extensionOrderingB],
    ['name' => 'concurrent_completion_verification', 'results' => $verifyResults],
];
$out = [
    'probe' => 'FLOW-12 MariaDB action-level race probe',
    'fixture_tag' => $tag,
    'race_group_count' => count($raceGroups),
    'race_groups' => $raceGroups,
    'concurrent_followup_initialization' => $initResults,
    'followup_root_rows' => MeetingDecisionFollowUp::where('meeting_id', $meeting->id)->count(),
    'concurrent_action_item_sequence_creation' => $sequenceResults,
    'created_action_item_ids' => array_values(array_map(static fn (array $identity) => $identity['id'], $sequenceIdentities)),
    'created_sequence_numbers' => array_values(array_map(static fn (array $identity) => $identity['sequence_number'], $sequenceIdentities)),
    'created_action_numbers' => array_values(array_map(static fn (array $identity) => $identity['action_number'], $sequenceIdentities)),
    'duplicate_sequence_count' => DB::table('meeting_action_items')->where('decision_followup_id', $followup->id)->select('sequence_number')->groupBy('sequence_number')->havingRaw('COUNT(*) > 1')->get()->count(),
    'action_item_rows_after_sequence_race' => MeetingActionItem::where('decision_followup_id', $followup->id)->count(),
    'concurrent_extension_requests' => $extensionResults,
    'concurrent_extension_review' => $extensionReviewResults,
    'pending_extension_rows_after_review' => DB::table('meeting_action_item_extension_requests')->where('action_item_id', $item->id)->where('status', 'PENDING')->count(),
    'extension_terminal_rows' => DB::table('meeting_action_item_extension_requests')->where('action_item_id', $item->id)->whereIn('status', ['APPROVED', 'REJECTED', 'WITHDRAWN'])->count(),
    'concurrent_progress_cancel' => $progressCancelResults,
    'progress_cancel_terminal_rows' => MeetingActionItem::whereKey($progressCancelItem->id)->whereIn('status', [MeetingActionItem::CANCELLED, MeetingActionItem::IN_PROGRESS])->count(),
    'submission_vs_reassignment_ordering_a' => [
        'before_assigned_employee_id' => $employee->id,
        'after_assigned_employee_id' => $submissionAFinal->assigned_employee_id,
        'submission_actor_employee_id' => $employee->id,
        'final_status' => $submissionAFinal->status,
        'lock_version' => $submissionAFinal->lock_version,
        'results' => $submissionReassignmentA,
        'loser_result_code' => $exceptionCode($submissionReassignmentA[1]),
    ],
    'submission_vs_reassignment_ordering_b' => [
        'before_assigned_employee_id' => $employee->id,
        'after_assigned_employee_id' => $submissionBFinal->assigned_employee_id,
        'submission_actor_employee_id' => $employee->id,
        'final_status' => $submissionBFinal->status,
        'lock_version' => $submissionBFinal->lock_version,
        'results' => $submissionReassignmentB,
        'loser_result_code' => $exceptionCode($submissionReassignmentB[1]),
    ],
    'concurrent_completion_verification_reassignment' => $completionReassignmentResults,
    'completion_reassignment_completed_rows' => MeetingActionItem::whereKey($terminalRaceItem->id)->where('status', MeetingActionItem::COMPLETED)->count(),
    'extension_vs_completion_ordering_a' => [
        'action_item_status' => $extensionAFinal->status,
        'old_due_date' => $oldDueA,
        'final_due_date' => $extensionAFinal->due_date?->format('Y-m-d'),
        'extension_status' => $extensionAFinalRequest->status,
        'pending_extension_count' => DB::table('meeting_action_item_extension_requests')->where('action_item_id', $extensionAItem->id)->where('status', 'PENDING')->count(),
        'results' => $extensionOrderingA,
        'audit_events' => $auditEventsForItem($extensionAItem->id),
    ],
    'extension_vs_completion_ordering_b' => [
        'action_item_status' => $extensionBFinal->status,
        'old_due_date' => $oldDueB,
        'final_due_date' => $extensionBFinal->due_date?->format('Y-m-d'),
        'extension_status' => $extensionBFinalRequest->status,
        'pending_extension_count' => DB::table('meeting_action_item_extension_requests')->where('action_item_id', $extensionBItem->id)->where('status', 'PENDING')->count(),
        'results' => $extensionOrderingB,
        'audit_events' => $auditEventsForItem($extensionBItem->id),
    ],
    'completed_items_with_pending_extensions' => $pendingTerminalExtensions,
    'pending_extensions_on_terminal_items' => $pendingTerminalExtensions,
    'overdue_check_applicability' => 'NOT_IMPLEMENTED / NOT_APPLICABLE: overdue is a read-time derived property; no scheduled checker or notification command exists',
    'concurrent_completion_verification' => $verifyResults,
    'completed_action_item_rows' => MeetingActionItem::where('decision_followup_id', $followup->id)->where('status', MeetingActionItem::COMPLETED)->count(),
    'completed_audit_rows' => DB::table('meeting_actions')->where('meeting_id', $meeting->id)->where('action', 'ACTION_ITEM_COMPLETED')->count(),
];
$out['ordering_invariants'] = [
    'extension_vs_completion_ordering_a' => $out['extension_vs_completion_ordering_a']['action_item_status'] === MeetingActionItem::COMPLETED
        && $out['extension_vs_completion_ordering_a']['extension_status'] === 'APPROVED'
        && $out['extension_vs_completion_ordering_a']['final_due_date'] === $newDueA ? 'PASS' : 'FAIL',
    'extension_vs_completion_ordering_b' => $out['extension_vs_completion_ordering_b']['action_item_status'] === MeetingActionItem::COMPLETED
        && $out['extension_vs_completion_ordering_b']['extension_status'] === 'WITHDRAWN'
        && $out['extension_vs_completion_ordering_b']['final_due_date'] === $oldDueB
        && $out['extension_vs_completion_ordering_b']['pending_extension_count'] === 0 ? 'PASS' : 'FAIL',
    'submission_vs_reassignment_ordering_a' => $submissionAFinal->status === MeetingActionItem::IN_PROGRESS && (int) $submissionAFinal->assigned_employee_id === (int) $alternateEmployee->id ? 'PASS' : 'FAIL',
    'submission_vs_reassignment_ordering_b' => $submissionBFinal->status === MeetingActionItem::PENDING_VERIFICATION && (int) $submissionBFinal->assigned_employee_id === (int) $employee->id ? 'PASS' : 'FAIL',
];
$out['pass'] = $out['race_group_count'] === 11
    && $out['followup_root_rows'] === 1
    && $out['action_item_rows_after_sequence_race'] === 9
    && count($out['created_action_item_ids']) === 2
    && count($out['created_sequence_numbers']) === 2
    && count(array_unique($out['created_sequence_numbers'])) === 2
    && $out['duplicate_sequence_count'] === 0
    && $out['pending_extension_rows_after_review'] === 0
    && $out['extension_terminal_rows'] === 1
    && $out['progress_cancel_terminal_rows'] === 1
    && $out['completed_items_with_pending_extensions'] === 0
    && $out['pending_extensions_on_terminal_items'] === 0
    && $out['ordering_invariants']['extension_vs_completion_ordering_a'] === 'PASS'
    && $out['ordering_invariants']['extension_vs_completion_ordering_b'] === 'PASS'
    && $out['ordering_invariants']['submission_vs_reassignment_ordering_a'] === 'PASS'
    && $out['ordering_invariants']['submission_vs_reassignment_ordering_b'] === 'PASS'
    && $out['completed_action_item_rows'] === 4;
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
