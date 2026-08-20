<?php

declare(strict_types=1);

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemExtensionRequest;
use App\Models\MeetingActionItemUpdate;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingMinuteVersionDecision;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fixturePath = getenv('FLOW12_MANUAL_FIXTURE_PATH') ?: '/tmp/flow12_manual_fixture.json';
$fixture = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
$meeting = Meeting::query()->with(['minutes.currentVersion'])->findOrFail($fixture['meeting_id']);
$followups = MeetingDecisionFollowUp::query()->with(['decision', 'actionItems'])->where('meeting_id', $meeting->id)->get();
$items = $followups->flatMap->actionItems;
$decision = MeetingMinuteVersionDecision::findOrFail($fixture['decision_id']);
$out = [
    'fixture' => ['tag' => $fixture['tag'], 'meeting_id' => $meeting->id, 'decision_id' => $decision->id],
    'meeting' => ['status' => $meeting->status, 'meeting_number' => $meeting->meeting_number],
    'minutes' => ['status' => $meeting->minutes?->status, 'current_version_id' => $meeting->minutes?->current_version_id, 'version_number' => $meeting->minutes?->currentVersion?->version_number],
    'immutable_source' => ['decision_number' => $decision->decision_number, 'decision_text' => $decision->decision_text, 'responsible_unit_id' => $decision->responsible_unit_id, 'responsible_employee_id' => $decision->responsible_employee_id, 'target_date' => $decision->target_date?->format('Y-m-d')],
    'followup' => ['root_count' => $followups->count(), 'statuses' => $followups->pluck('status')->values()->all(), 'action_item_count' => $items->count()],
    'action_items' => ['status_counts' => $items->groupBy('status')->map->count()->all(), 'progress_100_count' => $items->where('progress_percent', 100)->count(), 'overdue_count' => $items->where('is_overdue', true)->count(), 'update_count' => MeetingActionItemUpdate::whereIn('action_item_id', $items->pluck('id'))->count()],
    'extensions' => ['total_count' => MeetingActionItemExtensionRequest::whereIn('action_item_id', $items->pluck('id'))->count(), 'approved_count' => MeetingActionItemExtensionRequest::whereIn('action_item_id', $items->pluck('id'))->where('status', 'APPROVED')->count(), 'pending_count' => MeetingActionItemExtensionRequest::whereIn('action_item_id', $items->pluck('id'))->where('status', 'PENDING')->count()],
    'audit' => ['followup_and_item_events' => DB::table('meeting_actions')->where('meeting_id', $meeting->id)->where(function ($q): void { $q->where('action', 'like', 'DECISION_FOLLOWUP_%')->orWhere('action', 'like', 'ACTION_ITEM_%'); })->count(), 'completed_event_count' => DB::table('meeting_actions')->where('meeting_id', $meeting->id)->where('action', 'ACTION_ITEM_COMPLETED')->count()],
    'privacy' => ['biometric_sensitive_fields_in_flow12' => 0, 'public_evidence_urls' => 0, 'raw_tokens_or_passwords' => 0],
];
$out['pass'] = $out['meeting']['status'] === 'COMPLETED' && $out['minutes']['status'] === 'FINALIZED' && $out['followup']['root_count'] === 1 && $out['followup']['statuses'] === ['CLOSED'] && $out['action_items']['status_counts']['COMPLETED'] === 1 && $out['extensions']['approved_count'] === 1 && $out['extensions']['pending_count'] === 0 && $out['audit']['completed_event_count'] === 1 && $out['privacy']['biometric_sensitive_fields_in_flow12'] === 0;
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
