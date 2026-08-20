<?php

declare(strict_types=1);

use App\Models\AmmsNotification;
use App\Models\Meeting;
use App\Models\MeetingAction;
use App\Models\MeetingActionItem;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\MeetingMinutes;
use App\Models\MeetingRequest;
use App\Models\MeetingMinuteVersion;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fixture = json_decode(file_get_contents(dirname(__DIR__, 2).'/flow12_flow05_real_http_fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$runtime = json_decode(file_get_contents(dirname(__DIR__, 2).'/flow12_flow05_real_http_runtime.json'), true, 512, JSON_THROW_ON_ERROR);
$f = $fixture['fixture'];
$request = MeetingRequest::findOrFail($f['meeting_request_id']);
$meeting = Meeting::findOrFail($f['meeting_id']);
$minutes = MeetingMinutes::findOrFail($f['minutes_id']);
$version = MeetingMinuteVersion::findOrFail($f['finalized_minute_version_id']);
$decision = MeetingMinuteVersionDecision::findOrFail($f['decision_id']);
$followup = MeetingDecisionFollowUp::findOrFail($runtime['followup_id']);
$item = MeetingActionItem::findOrFail($runtime['action_item_id']);
$actions = MeetingAction::where('meeting_id', $meeting->id)->where(function ($q) use ($followup, $item): void {
    $q->whereJsonContains('metadata->decision_followup_id', $followup->id)->orWhereJsonContains('metadata->action_item_id', $item->id);
})->orderBy('id')->get();
$notifications = AmmsNotification::where(function ($q) use ($followup, $item): void {
    $q->whereJsonContains('data->decision_followup_id', $followup->id)->orWhereJsonContains('data->action_item_id', $item->id);
})->orderBy('id')->get();
$out = [
    'source_after' => [
        'request' => ['id' => $request->id, 'flow_type' => $request->flow_type, 'status' => $request->status, 'meeting_id' => $request->meeting()->value('id')],
        'meeting' => ['id' => $meeting->id, 'status' => $meeting->status, 'meeting_number' => $meeting->meeting_number],
        'minutes' => ['id' => $minutes->id, 'status' => $minutes->status, 'current_version_id' => $minutes->current_version_id],
        'version' => ['id' => $version->id, 'version_number' => $version->version_number, 'summary' => $version->summary, 'schedule_version_id' => $version->schedule_version_id, 'participant_version_id' => $version->participant_version_id],
        'decision' => ['id' => $decision->id, 'decision_number' => $decision->decision_number, 'decision_text' => $decision->decision_text, 'sequence_number' => $decision->sequence_number, 'responsible_unit_id' => $decision->responsible_unit_id, 'responsible_employee_id' => $decision->responsible_employee_id],
        'schedule_version_count' => $meeting->scheduleVersions()->count(),
        'participant_version_count' => $meeting->participantVersions()->count(),
        'meeting_count' => Meeting::where('id', $meeting->id)->count(),
        'minutes_count' => MeetingMinutes::where('id', $minutes->id)->count(),
        'decision_count' => MeetingMinuteVersionDecision::where('id', $decision->id)->count(),
    ],
    'followup' => ['id' => $followup->id, 'classification' => $followup->classification, 'status' => $followup->status, 'decision_id' => $followup->meeting_decision_id],
    'action_item' => ['id' => $item->id, 'status' => $item->status, 'progress_percent' => (int) $item->progress_percent, 'decision_followup_id' => $item->decision_followup_id, 'responsible_unit_id' => $item->responsible_unit_id, 'assigned_employee_id' => $item->assigned_employee_id],
    'audit' => ['count' => $actions->count(), 'actions' => $actions->pluck('action')->values()->all(), 'rows' => $actions->map(fn ($a) => ['id' => $a->id, 'actor_id' => $a->actor_id, 'action' => $a->action, 'metadata' => $a->metadata])->values()->all()],
    'notifications' => ['count' => $notifications->count(), 'types' => $notifications->pluck('type')->values()->all(), 'rows' => $notifications->map(fn ($n) => ['id' => $n->id, 'user_id' => $n->user_id, 'type' => $n->type, 'data' => $n->data])->values()->all()],
];
file_put_contents(dirname(__DIR__, 2).'/flow12_flow05_real_http_after_snapshot.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
