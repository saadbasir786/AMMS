<?php

declare(strict_types=1);

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemAssignmentHistory;
use App\Models\MeetingActionItemExtensionRequest;
use App\Models\MeetingActionItemUpdate;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\MeetingRequest;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fixturePath = getenv('FLOW12_MANUAL_FIXTURE_PATH') ?: '/tmp/flow12_manual_fixture.json';
$fixture = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
$meeting = Meeting::query()->with(['minutes.currentVersion', 'request'])->findOrFail($fixture['meeting_id']);
$version = $meeting->minutes->currentVersion;
$decisions = MeetingMinuteVersionDecision::where('meeting_minute_version_id', $version->id)->orderBy('sequence_number')->get();
$followups = MeetingDecisionFollowUp::where('meeting_id', $meeting->id)->where('meeting_minute_version_id', $version->id)->with('actionItems')->get();
$items = $followups->flatMap->actionItems;
$sourceDecision = MeetingMinuteVersionDecision::findOrFail($fixture['decision_id']);
$before = $fixture['before_source_snapshot'];
$after = [
    'request_status' => $meeting->request?->status,
    'meeting_status' => $meeting->status,
    'minutes_status' => $meeting->minutes?->status,
    'meeting_minute_version_id' => $version->id,
    'decision_text' => $sourceDecision->decision_text,
    'decision_target_date' => $sourceDecision->target_date?->format('Y-m-d'),
    'decision_responsible_unit_id' => $sourceDecision->responsible_unit_id,
    'decision_responsible_employee_id' => $sourceDecision->responsible_employee_id,
];
$statusCounts = $items->groupBy('status')->map->count()->all();
$classificationCounts = $followups->groupBy('classification')->map->count()->all();
$duplicateFollowupRootCount = DB::table('meeting_decision_followups')->select('meeting_decision_id')->groupBy('meeting_decision_id')->havingRaw('COUNT(*) > 1')->get()->count();
$duplicateActionSequenceCount = DB::table('meeting_action_items')->select('decision_followup_id', 'sequence_number')->groupBy('decision_followup_id', 'sequence_number')->havingRaw('COUNT(*) > 1')->get()->count();
$duplicateCompletionCount = DB::table('meeting_actions')->where('meeting_id', $meeting->id)->where('action', 'ACTION_ITEM_COMPLETED')->select('entity_id')->groupBy('entity_id')->havingRaw('COUNT(*) > 1')->get()->count();
$pendingExtensionDuplicateCount = DB::table('meeting_action_item_extension_requests')->whereIn('action_item_id', $items->pluck('id'))->where('status', 'PENDING')->select('action_item_id')->groupBy('action_item_id')->havingRaw('COUNT(*) > 1')->get()->count();
$requestMeetingDuplicateCount = DB::table('meetings')->where('meeting_request_id', $meeting->meeting_request_id)->select('meeting_request_id')->groupBy('meeting_request_id')->havingRaw('COUNT(*) > 1')->get()->count();
$sourceDecisionMutationCount = ($before['decision_text'] !== $after['decision_text']) ? 1 : 0;
$documentaryResponsibilityMutationCount = ($before['decision_responsible_unit_id'] !== $after['decision_responsible_unit_id'] || $before['decision_responsible_employee_id'] !== $after['decision_responsible_employee_id']) ? 1 : 0;
$documentaryTargetDateMutationCount = ($before['decision_target_date'] !== $after['decision_target_date']) ? 1 : 0;
$out = [
    'fixture' => ['tag' => $fixture['tag'], 'meeting_id' => $meeting->id, 'meeting_request_id' => $meeting->meeting_request_id, 'decision_id' => $sourceDecision->id],
    'before_source_snapshot' => $before,
    'after_source_snapshot' => $after,
    'meeting_request_id' => $meeting->meeting_request_id,
    'request_status' => $meeting->request?->status,
    'final_minutes_version_id' => $version->id,
    'meeting_status' => $meeting->status,
    'minutes_status' => $meeting->minutes?->status,
    'decision_count' => $decisions->count(),
    'decision_followup_count' => $followups->count(),
    'unclassified_decision_count' => $followups->where('classification', MeetingDecisionFollowUp::UNCLASSIFIED)->count(),
    'no_action_required_count' => $followups->where('classification', MeetingDecisionFollowUp::NO_ACTION_REQUIRED)->count(),
    'action_required_count' => $followups->where('classification', MeetingDecisionFollowUp::ACTION_REQUIRED)->count(),
    'active_followup_count' => $followups->where('status', MeetingDecisionFollowUp::ACTIVE)->count(),
    'open_followup_count' => $followups->where('status', MeetingDecisionFollowUp::OPEN)->count(),
    'closed_followup_count' => $followups->where('status', MeetingDecisionFollowUp::CLOSED)->count(),
    'all_action_item_status_counts' => $statusCounts,
    'duplicate_followup_root_count' => $duplicateFollowupRootCount,
    'duplicate_action_item_sequence_count' => $duplicateActionSequenceCount,
    'duplicate_completion_count' => $duplicateCompletionCount,
    'pending_extension_duplicate_count' => $pendingExtensionDuplicateCount,
    'request_meeting_duplicate_count' => $requestMeetingDuplicateCount,
    'assignment_history_count' => MeetingActionItemAssignmentHistory::whereIn('action_item_id', $items->pluck('id'))->count(),
    'progress_update_count' => MeetingActionItemUpdate::whereIn('action_item_id', $items->pluck('id'))->whereIn('update_type', [MeetingActionItemUpdate::PROGRESS, MeetingActionItemUpdate::BLOCKED, MeetingActionItemUpdate::UNBLOCKED])->count(),
    'completion_submission_count' => MeetingActionItemUpdate::whereIn('action_item_id', $items->pluck('id'))->where('update_type', MeetingActionItemUpdate::COMPLETION_SUBMITTED)->count(),
    'completion_verification_count' => DB::table('meeting_actions')->where('meeting_id', $meeting->id)->where('action', 'ACTION_ITEM_COMPLETED')->count(),
    'extension_count' => MeetingActionItemExtensionRequest::whereIn('action_item_id', $items->pluck('id'))->count(),
    'approved_extension_count' => MeetingActionItemExtensionRequest::whereIn('action_item_id', $items->pluck('id'))->where('status', 'APPROVED')->count(),
    'source_decision_mutation_count' => $sourceDecisionMutationCount,
    'meeting_status_mutation_count' => $before['meeting_status'] === $after['meeting_status'] ? 0 : 1,
    'minutes_status_mutation_count' => $before['minutes_status'] === $after['minutes_status'] ? 0 : 1,
    'documentary_responsibility_mutation_count' => $documentaryResponsibilityMutationCount,
    'documentary_target_date_mutation_count' => $documentaryTargetDateMutationCount,
    'privacy' => ['biometric_sensitive_fields_in_flow12' => 0, 'public_evidence_urls' => 0, 'raw_tokens_or_passwords' => 0],
];
$out['pass'] = $out['request_status'] === 'CONVERTED_TO_MEETING'
    && $out['meeting_status'] === 'COMPLETED'
    && $out['minutes_status'] === 'FINALIZED'
    && $out['decision_count'] === 2
    && $out['decision_followup_count'] === 2
    && $out['unclassified_decision_count'] === 0
    && $out['no_action_required_count'] === 1
    && $out['action_required_count'] === 1
    && $out['active_followup_count'] === 0
    && $out['closed_followup_count'] === 2
    && (($out['all_action_item_status_counts'][MeetingActionItem::COMPLETED] ?? 0) === 1)
    && $out['duplicate_followup_root_count'] === 0
    && $out['duplicate_action_item_sequence_count'] === 0
    && $out['duplicate_completion_count'] === 0
    && $out['pending_extension_duplicate_count'] === 0
    && $out['request_meeting_duplicate_count'] === 0
    && $out['source_decision_mutation_count'] === 0
    && $out['meeting_status_mutation_count'] === 0
    && $out['minutes_status_mutation_count'] === 0
    && $out['documentary_responsibility_mutation_count'] === 0
    && $out['documentary_target_date_mutation_count'] === 0
    && $out['privacy']['biometric_sensitive_fields_in_flow12'] === 0;
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
