<?php

declare(strict_types=1);

namespace App\Actions\ActionItems\Concerns;

use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemUpdate;
use App\Models\MeetingActionItemEvidence;
use App\Models\MeetingActionItemExtensionRequest;
use App\Models\MeetingActionItemAssignmentHistory;
use App\Models\MeetingAction;
use App\Models\MeetingDecisionFollowUp;
use App\Models\AmmsNotification;
use App\Models\User;
use App\Support\DecisionFollowUpGovernanceResolver;
use Illuminate\Support\Str;

trait Flow12ActionSupport
{
    protected function governance(): DecisionFollowUpGovernanceResolver
    {
        return app(DecisionFollowUpGovernanceResolver::class);
    }

    protected function auditFollowup(MeetingDecisionFollowUp $followup, User $actor, string $event, ?string $comment = null, array $metadata = []): void
    {
        MeetingAction::create([
            'meeting_id' => $followup->meeting_id,
            'actor_id' => $actor->id,
            'action' => $event,
            'comment' => $comment,
            'metadata' => array_merge(['decision_followup_id' => $followup->id], $metadata),
            'created_at' => now(),
        ]);
    }

    protected function auditItem(MeetingActionItem $item, User $actor, string $event, ?string $comment = null, array $metadata = []): void
    {
        $item->loadMissing('followup');
        MeetingAction::create([
            'meeting_id' => $item->followup->meeting_id,
            'actor_id' => $actor->id,
            'action' => $event,
            'comment' => $comment,
            'metadata' => array_merge(['action_item_id' => $item->id, 'decision_followup_id' => $item->decision_followup_id], $metadata),
            'created_at' => now(),
        ]);
    }

    protected function updateItem(MeetingActionItem $item, User $actor, string $type, ?int $progress = null, ?string $note = null): MeetingActionItemUpdate
    {
        return $item->updates()->create([
            'uuid' => (string) Str::uuid(),
            'update_type' => $type,
            'progress_percent' => $progress,
            'note' => $note,
            'created_by_user_id' => $actor->id,
        ]);
    }

    protected function touchLock(MeetingActionItem $item): void
    {
        $item->lock_version = (int) $item->lock_version + 1;
        $item->save();
    }

    protected function assertProgress(?int $progress): int
    {
        abort_unless($progress !== null && $progress >= 0 && $progress <= 100, 422, 'ACTION_ITEM_PROGRESS_INVALID');
        return $progress;
    }

    protected function assertReason(?string $reason, string $code): string
    {
        $reason = trim((string) $reason);
        abort_unless($reason !== '', 422, $code);
        return $reason;
    }

    protected function markExtensionPending(MeetingActionItemExtensionRequest $extension): void
    {
        $extension->pending_marker = 1;
        $extension->save();
    }

    /**
     * Resolve pending extension requests when a terminal completion wins.
     * Must be called inside the caller's transaction after the Action Item row is locked.
     */
    protected function withdrawPendingExtensions(MeetingActionItem $item, User $actor): int
    {
        $reason = 'ACTION_ITEM_COMPLETED_BEFORE_EXTENSION_REVIEW';
        $extensions = MeetingActionItemExtensionRequest::query()
            ->where('action_item_id', $item->id)
            ->where('status', MeetingActionItemExtensionRequest::PENDING)
            ->lockForUpdate()
            ->get();

        foreach ($extensions as $extension) {
            $extension->update([
                'status' => MeetingActionItemExtensionRequest::WITHDRAWN,
                'pending_marker' => null,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'review_reason' => $reason,
            ]);
            $this->auditItem($item, $actor, 'ACTION_ITEM_EXTENSION_WITHDRAWN', $reason, [
                'extension_request_id' => $extension->id,
                'resolution' => MeetingActionItemExtensionRequest::WITHDRAWN,
            ]);
            $this->notifyUser(
                $extension->requested_by_user_id,
                'ACTION_ITEM_EXTENSION_WITHDRAWN',
                'درخواست تمدید بسته شد',
                'تکمیل اقدام قبل از بررسی درخواست تمدید تأیید شد؛ درخواست تمدید بسته شد.',
                ['action_item_id' => $item->id, 'extension_request_id' => $extension->id, 'reason' => $reason],
            );
        }

        return $extensions->count();
    }

    protected function notifyUser(?int $userId, string $type, string $title, string $message, array $data = []): void
    {
        if (!$userId) return;
        AmmsNotification::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'message' => $message, 'data' => $data]);
    }

    protected function notifyOwners(MeetingDecisionFollowUp $followup, string $type, string $title, string $message, array $data = []): void
    {
        $meeting = $followup->meeting;
        if (!$meeting) return;
        $role = $this->governance()->ownerRole($meeting);
        $unitId = $this->governance()->ownerUnitId($meeting);
        User::query()->where('status', 'ACTIVE')->get()->each(function (User $user) use ($role, $unitId, $type, $title, $message, $data): void {
            if ($user->hasScopedRole($role, $unitId)) $this->notifyUser($user->id, $type, $title, $message, $data);
        });
    }

    protected function notifyUnit(MeetingActionItem $item, string $type, string $title, string $message, array $data = []): void
    {
        User::query()->where('status', 'ACTIVE')->get()->each(function (User $user) use ($item, $type, $title, $message, $data): void {
            if ($user->hasScopedRole('DIRECTOR', (int) $item->responsible_unit_id) || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $item->responsible_unit_id)) {
                $this->notifyUser($user->id, $type, $title, $message, $data);
            }
        });
    }

    protected function recordAssignment(MeetingActionItem $item, User $actor, int $toUnitId, ?int $toEmployeeId, string $reason): void
    {
        $item->assignmentHistory()->create([
            'uuid' => (string) Str::uuid(),
            'from_unit_id' => $item->responsible_unit_id,
            'to_unit_id' => $toUnitId,
            'from_employee_id' => $item->assigned_employee_id,
            'to_employee_id' => $toEmployeeId,
            'reason' => $reason,
            'changed_by_user_id' => $actor->id,
            'changed_at' => now(),
        ]);
    }
}
