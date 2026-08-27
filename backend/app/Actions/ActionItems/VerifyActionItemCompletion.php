<?php

declare(strict_types=1);

namespace App\Actions\ActionItems;

use App\Actions\ActionItems\Concerns\ActionItemWorkflowSupport;
use App\Models\MeetingActionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VerifyActionItemCompletion
{
    use ActionItemWorkflowSupport;

    public function execute(MeetingActionItem $item, User $actor): MeetingActionItem
    {
        $item->loadMissing('followup.meeting');
        abort_unless($this->governance()->mayVerify($item, $actor), 403, 'UNAUTHORIZED_ACTION_ITEM_VERIFICATION');
        return DB::transaction(function () use ($item, $actor): MeetingActionItem {
            $locked = MeetingActionItem::query()->with('followup')->lockForUpdate()->findOrFail($item->id);
            abort_unless($locked->status === MeetingActionItem::PENDING_VERIFICATION, 409, 'ACTION_ITEM_NOT_PENDING_VERIFICATION');
            $locked->update(['status' => MeetingActionItem::COMPLETED, 'progress_percent' => 100, 'completed_at' => now(), 'completed_by_user_id' => $actor->id, 'lock_version' => $locked->lock_version + 1]);
            $this->withdrawPendingExtensions($locked, $actor);
            $this->auditItem($locked, $actor, 'ACTION_ITEM_COMPLETED');
            $this->notifyUser($locked->submitted_by_user_id, 'ACTION_ITEM_COMPLETED', 'تکمیل اقدام تأیید شد', 'تکمیل اقدام عملیاتی شما تأیید شد.', ['action_item_id' => $locked->id]);
            $followup = $locked->followup->fresh(['actionItems']);
            $this->governance()->refreshFollowupStatus($followup);
            if ($followup->status === 'CLOSED') $this->auditFollowup($followup, $actor, 'DECISION_FOLLOWUP_CLOSED');
            return $locked->fresh(['followup.decision', 'responsibleUnit', 'assignedEmployee']);
        });
    }
}
