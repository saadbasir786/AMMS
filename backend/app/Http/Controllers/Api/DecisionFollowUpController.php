<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Flow12\CancelMeetingActionItem;
use App\Actions\Flow12\ChangeActionItemAssignment;
use App\Actions\Flow12\ClassifyMeetingDecision;
use App\Actions\Flow12\CloseDecisionFollowUp;
use App\Actions\Flow12\CreateMeetingActionItem;
use App\Actions\Flow12\InitializeMeetingDecisionFollowUp;
use App\Actions\Flow12\MarkActionItemBlocked;
use App\Actions\Flow12\RecordActionItemProgress;
use App\Actions\Flow12\RequestActionItemDueDateExtension;
use App\Actions\Flow12\ReturnActionItemCompletion;
use App\Actions\Flow12\ReviewActionItemDueDateExtension;
use App\Actions\Flow12\StartMeetingActionItem;
use App\Actions\Flow12\SubmitActionItemCompletion;
use App\Actions\Flow12\UnblockActionItem;
use App\Actions\Flow12\UploadActionItemEvidence;
use App\Actions\Flow12\VerifyActionItemCompletion;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActionItems\ActionItemExtensionRequest;
use App\Http\Requests\ActionItems\ActionItemReasonRequest;
use App\Http\Requests\ActionItems\ChangeActionItemAssignmentRequest;
use App\Http\Requests\ActionItems\ClassifyMeetingDecisionRequest;
use App\Http\Requests\ActionItems\CreateMeetingActionItemRequest;
use App\Http\Requests\ActionItems\RecordActionItemProgressRequest;
use App\Http\Requests\ActionItems\ReviewActionItemExtensionRequest;
use App\Http\Requests\ActionItems\SubmitActionItemCompletionRequest;
use App\Http\Requests\ActionItems\UnblockActionItemRequest;
use App\Http\Resources\MeetingActionItemEvidenceResource;
use App\Http\Resources\MeetingActionItemExtensionResource;
use App\Http\Resources\MeetingActionItemResource;
use App\Http\Resources\MeetingDecisionFollowUpResource;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemEvidence;
use App\Models\MeetingActionItemExtensionRequest;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\User;
use App\Policies\MeetingPolicy;
use App\Support\DecisionFollowUpGovernanceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DecisionFollowUpController extends Controller
{
    public function __construct(private readonly DecisionFollowUpGovernanceResolver $governance) {}

    private function meetingView(User $actor, Meeting $meeting): void
    {
        abort_unless($actor->isActive() && $actor->can('decision.followup.view') && app(MeetingPolicy::class)->view($actor, $meeting), 403, 'UNAUTHORIZED_DECISION_FOLLOWUP');
    }

    private function followupForDecision(MeetingMinuteVersionDecision $decision): MeetingDecisionFollowUp
    {
        $followup = MeetingDecisionFollowUp::query()->with('meeting')->where('meeting_decision_id', $decision->id)->firstOrFail();
        abort_unless((int) $followup->meeting_minute_version_id === (int) $decision->meeting_minute_version_id, 409, 'FOLLOWUP_SOURCE_DECISION_MISMATCH');
        return $followup;
    }

    public function meeting(Meeting $meeting): JsonResponse
    {
        $actor = request()->user();
        $this->meetingView($actor, $meeting);
        [$minutes, $version] = $this->governance->source($meeting);
        $followups = MeetingDecisionFollowUp::query()->with(['decision', 'actionItems.responsibleUnit', 'actionItems.assignedEmployee'])->where('meeting_id', $meeting->id)->where('meeting_minute_version_id', $version->id)->orderBy('id')->get();
        $items = $followups->flatMap->actionItems;
        return response()->json(['data' => [
            'meeting' => ['id' => $meeting->id, 'meeting_number' => $meeting->meeting_number, 'title' => $meeting->title, 'status' => $meeting->status],
            'minutes' => ['id' => $minutes->id, 'status' => $minutes->status, 'final_version_id' => $version->id, 'version_number' => $version->version_number, 'finalized_at' => $minutes->finalized_at],
            'decision_count' => $version->decisions()->count(),
            'followup_initialized' => $followups->count() > 0,
            'summary' => ['total_decisions' => $version->decisions()->count(), 'action_required' => $followups->where('classification', MeetingDecisionFollowUp::ACTION_REQUIRED)->count(), 'no_action_required' => $followups->where('classification', MeetingDecisionFollowUp::NO_ACTION_REQUIRED)->count(), 'open' => $items->where('status', MeetingActionItem::OPEN)->count(), 'in_progress' => $items->where('status', MeetingActionItem::IN_PROGRESS)->count(), 'blocked' => $items->where('status', MeetingActionItem::BLOCKED)->count(), 'pending_verification' => $items->where('status', MeetingActionItem::PENDING_VERIFICATION)->count(), 'completed' => $items->where('status', MeetingActionItem::COMPLETED)->count(), 'overdue' => $items->where('is_overdue', true)->count()],
            'followups' => MeetingDecisionFollowUpResource::collection($followups),
        ]]);
    }

    public function initialize(Meeting $meeting, InitializeMeetingDecisionFollowUp $action): JsonResponse
    {
        $this->meetingView(request()->user(), $meeting);
        $result = $action->execute($meeting, request()->user());
        return response()->json(['data' => MeetingDecisionFollowUpResource::collection($result)], 201);
    }

    public function classify(ClassifyMeetingDecisionRequest $request, MeetingMinuteVersionDecision $decision, ClassifyMeetingDecision $action): MeetingDecisionFollowUpResource
    {
        $followup = $this->followupForDecision($decision);
        $this->authorize('classify', $followup);
        return new MeetingDecisionFollowUpResource($action->execute($followup, $request->user(), $request->string('classification')->toString(), $request->input('reason')));
    }

    public function storeActionItem(CreateMeetingActionItemRequest $request, MeetingMinuteVersionDecision $decision, CreateMeetingActionItem $action): JsonResponse
    {
        $followup = $this->followupForDecision($decision);
        $this->authorize('createActionItem', $followup);
        return (new MeetingActionItemResource($action->execute($followup, $request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor->isActive() && $actor->can('action-item.view'), 403, 'UNAUTHORIZED_ACTION_ITEM_VIEW');
        $query = MeetingActionItem::query()->with(['followup.meeting', 'followup.version', 'followup.decision', 'responsibleUnit', 'assignedEmployee']);
        $query->where(function ($q) use ($actor): void {
            $q->where('assigned_employee_id', $actor->employee_id ?: 0);
            if ($actor->employee_id) $q->orWhere('responsible_unit_id', $actor->employee->organizational_unit_id);
        });
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        if ($request->boolean('overdue')) $query->whereNotIn('status', [MeetingActionItem::COMPLETED, MeetingActionItem::CANCELLED])->whereDate('due_date', '<', now()->toDateString());
        return response()->json(['data' => MeetingActionItemResource::collection($query->latest('id')->paginate((int) min(100, max(1, $request->integer('per_page', 25)))))]);
    }

    public function show(MeetingActionItem $actionItem): MeetingActionItemResource
    {
        $actionItem->load(['followup.meeting', 'followup.version', 'followup.decision', 'responsibleUnit', 'assignedEmployee']);
        $this->authorize('view', $actionItem);
        return new MeetingActionItemResource($actionItem);
    }

    public function start(MeetingActionItem $actionItem, StartMeetingActionItem $action): MeetingActionItemResource
    {
        $this->authorize('start', $actionItem);
        return new MeetingActionItemResource($action->execute($actionItem, request()->user()));
    }

    public function progress(RecordActionItemProgressRequest $request, MeetingActionItem $actionItem, RecordActionItemProgress $action): MeetingActionItemResource
    {
        $this->authorize('progress', $actionItem);
        return new MeetingActionItemResource($action->execute($actionItem, $request->user(), (int) $request->integer('progress_percent'), $request->input('note')));
    }

    public function block(ActionItemReasonRequest $request, MeetingActionItem $actionItem, MarkActionItemBlocked $action): MeetingActionItemResource
    {
        $this->authorize('block', $actionItem);
        return new MeetingActionItemResource($action->execute($actionItem, $request->user(), (string) $request->input('reason')));
    }

    public function unblock(UnblockActionItemRequest $request, MeetingActionItem $actionItem, UnblockActionItem $action): MeetingActionItemResource
    {
        $this->authorize('progress', $actionItem);
        return new MeetingActionItemResource($action->execute($actionItem, $request->user(), (string) $request->input('note')));
    }

    public function assignment(ChangeActionItemAssignmentRequest $request, MeetingActionItem $actionItem, ChangeActionItemAssignment $action): MeetingActionItemResource
    {
        $this->authorize('assignment', $actionItem);
        if ($request->filled('responsible_unit_id') && (int) $request->integer('responsible_unit_id') !== (int) $actionItem->responsible_unit_id) $this->authorize('changeUnit', $actionItem);
        return new MeetingActionItemResource($action->execute($actionItem, $request->user(), $request->integer('responsible_unit_id') ?: null, $request->integer('assigned_employee_id') ?: null, (string) $request->input('reason')));
    }

    public function extension(ActionItemExtensionRequest $request, MeetingActionItem $actionItem, RequestActionItemDueDateExtension $action): JsonResponse
    {
        $this->authorize('extensionRequest', $actionItem);
        return (new MeetingActionItemExtensionResource($action->execute($actionItem, $request->user(), (string) $request->input('requested_due_date'), (string) $request->input('reason'))))->response()->setStatusCode(201);
    }

    public function reviewExtension(ReviewActionItemExtensionRequest $request, MeetingActionItemExtensionRequest $extension, ReviewActionItemDueDateExtension $action): MeetingActionItemExtensionResource
    {
        $extension->load('actionItem.followup.meeting');
        $this->authorize('extensionReview', $extension);
        return new MeetingActionItemExtensionResource($action->execute($extension, $request->user(), $request->boolean('approve'), $request->input('reason')));
    }

    public function approveExtension(Request $request, MeetingActionItemExtensionRequest $extension, ReviewActionItemDueDateExtension $action): MeetingActionItemExtensionResource
    {
        $extension->load('actionItem.followup.meeting');
        $this->authorize('extensionReview', $extension);
        return new MeetingActionItemExtensionResource($action->execute($extension, $request->user(), true));
    }

    public function rejectExtension(ActionItemReasonRequest $request, MeetingActionItemExtensionRequest $extension, ReviewActionItemDueDateExtension $action): MeetingActionItemExtensionResource
    {
        $extension->load('actionItem.followup.meeting');
        $this->authorize('extensionReview', $extension);
        return new MeetingActionItemExtensionResource($action->execute($extension, $request->user(), false, (string) $request->input('reason')));
    }

    public function submit(SubmitActionItemCompletionRequest $request, MeetingActionItem $actionItem, SubmitActionItemCompletion $action): MeetingActionItemResource
    {
        $this->authorize('submitCompletion', $actionItem);
        return new MeetingActionItemResource($action->execute($actionItem, $request->user(), (string) $request->input('completion_note'), (array) $request->input('evidence', [])));
    }

    public function verify(MeetingActionItem $actionItem, VerifyActionItemCompletion $action): MeetingActionItemResource
    {
        $this->authorize('verifyCompletion', $actionItem);
        return new MeetingActionItemResource($action->execute($actionItem, request()->user()));
    }

    public function returnCompletion(ActionItemReasonRequest $request, MeetingActionItem $actionItem, ReturnActionItemCompletion $action): MeetingActionItemResource
    {
        $this->authorize('verifyCompletion', $actionItem);
        return new MeetingActionItemResource($action->execute($actionItem, $request->user(), (string) $request->input('reason')));
    }

    public function cancel(ActionItemReasonRequest $request, MeetingActionItem $actionItem, CancelMeetingActionItem $action): MeetingActionItemResource
    {
        $this->authorize('cancel', $actionItem);
        return new MeetingActionItemResource($action->execute($actionItem, $request->user(), (string) $request->input('reason')));
    }

    public function close(MeetingMinuteVersionDecision $decision, CloseDecisionFollowUp $action): MeetingDecisionFollowUpResource
    {
        $followup = $this->followupForDecision($decision);
        $this->authorize('close', $followup);
        return new MeetingDecisionFollowUpResource($action->execute($followup, request()->user()));
    }

    public function uploadEvidence(Request $request, MeetingActionItem $actionItem, UploadActionItemEvidence $action): MeetingActionItemEvidenceResource
    {
        $this->authorize('evidenceUpload', $actionItem);
        abort_unless($request->file('evidence') instanceof UploadedFile, 422, 'ACTION_ITEM_EVIDENCE_REQUIRED');
        return new MeetingActionItemEvidenceResource($action->execute($actionItem, $request->user(), $request->file('evidence'), $request->integer('action_item_update_id') ?: null));
    }

    public function downloadEvidence(MeetingActionItem $actionItem, MeetingActionItemEvidence $evidence)
    {
        abort_unless((int) $evidence->action_item_id === (int) $actionItem->id, 404, 'ACTION_ITEM_EVIDENCE_NOT_FOUND');
        $evidence->load('actionItem.followup.meeting');
        $this->authorize('evidenceView', $evidence);
        abort_unless(Storage::disk($evidence->storage_disk)->exists($evidence->storage_path), 404, 'ACTION_ITEM_EVIDENCE_NOT_FOUND');
        return Storage::disk($evidence->storage_disk)->download($evidence->storage_path, $evidence->original_filename, ['Content-Type' => $evidence->mime_type]);
    }
}
