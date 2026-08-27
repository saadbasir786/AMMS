<?php

declare(strict_types=1);

namespace App\Actions\ActionItems;

use App\Actions\ActionItems\Concerns\ActionItemWorkflowSupport;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemExtensionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReviewActionItemDueDateExtension
{
    use ActionItemWorkflowSupport;

    public function execute(MeetingActionItemExtensionRequest $extension, User $actor, bool $approve, ?string $reason = null): MeetingActionItemExtensionRequest
    {
        $extension->loadMissing('actionItem.followup.meeting');
        $item = $extension->actionItem;
        abort_unless($item && $this->governance()->mayReviewExtension($item, $actor), 403, 'UNAUTHORIZED_ACTION_ITEM_EXTENSION_REVIEW');
        if (!$approve) $reason = $this->assertReason($reason, 'ACTION_ITEM_EXTENSION_REVIEW_REASON_REQUIRED');

        return DB::transaction(function () use ($extension, $actor, $approve, $reason): MeetingActionItemExtensionRequest {
            $lockedExtension = MeetingActionItemExtensionRequest::query()->with('actionItem.followup.meeting')->lockForUpdate()->findOrFail($extension->id);
            abort_unless($lockedExtension->status === MeetingActionItemExtensionRequest::PENDING, 409, 'ACTION_ITEM_EXTENSION_NOT_PENDING');
            $lockedItem = MeetingActionItem::query()->with('followup')->lockForUpdate()->findOrFail($lockedExtension->action_item_id);
            if ($approve) {
                abort_unless(!$lockedItem->isTerminal(), 409, 'ACTION_ITEM_INVALID_STATE');
                abort_unless($lockedItem->due_date?->toDateString() === $lockedExtension->current_due_date?->toDateString(), 409, 'ACTION_ITEM_EXTENSION_STALE_DATE');
                $lockedItem->update(['due_date' => $lockedExtension->requested_due_date, 'lock_version' => $lockedItem->lock_version + 1]);
            }
            $lockedExtension->update([
                'status' => $approve ? MeetingActionItemExtensionRequest::APPROVED : MeetingActionItemExtensionRequest::REJECTED,
                'pending_marker' => null,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
                'review_reason' => $reason,
            ]);
            $this->auditItem($lockedItem, $actor, $approve ? 'ACTION_ITEM_EXTENSION_APPROVED' : 'ACTION_ITEM_EXTENSION_REJECTED', $reason, ['extension_request_id' => $lockedExtension->id]);
            $this->notifyUser($lockedExtension->requested_by_user_id, $approve ? 'ACTION_ITEM_EXTENSION_APPROVED' : 'ACTION_ITEM_EXTENSION_REJECTED', $approve ? 'تمدید مهلت تأیید شد' : 'تمدید مهلت رد شد', $approve ? 'درخواست تمدید مهلت شما تأیید شد.' : 'درخواست تمدید مهلت شما رد شد.', ['action_item_id' => $lockedItem->id]);
            return $lockedExtension->fresh(['actionItem']);
        });
    }
}
