<?php

declare(strict_types=1);

namespace App\Actions\Flow12;

use App\Actions\Flow12\Concerns\Flow12ActionSupport;
use App\Models\MeetingActionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelMeetingActionItem
{
    use Flow12ActionSupport;

    public function execute(MeetingActionItem $item, User $actor, string $reason): MeetingActionItem
    {
        $item->loadMissing('followup.meeting');
        abort_unless($this->governance()->mayCancel($item, $actor), 403, 'UNAUTHORIZED_ACTION_ITEM_CANCEL');
        $reason = $this->assertReason($reason, 'ACTION_ITEM_CANCEL_REASON_REQUIRED');
        return DB::transaction(function () use ($item, $actor, $reason): MeetingActionItem {
            $locked = MeetingActionItem::query()->with('followup')->lockForUpdate()->findOrFail($item->id);
            abort_unless(in_array($locked->status, [MeetingActionItem::OPEN, MeetingActionItem::IN_PROGRESS, MeetingActionItem::BLOCKED], true), 409, 'ACTION_ITEM_CANNOT_CANCEL_COMPLETED');
            $locked->update(['status' => MeetingActionItem::CANCELLED, 'cancelled_at' => now(), 'cancelled_by_user_id' => $actor->id, 'cancel_reason' => $reason, 'lock_version' => $locked->lock_version + 1]);
            $this->auditItem($locked, $actor, 'ACTION_ITEM_CANCELLED', $reason);
            $followup = $locked->followup->fresh(['actionItems']);
            $this->governance()->refreshFollowupStatus($followup);
            if ($followup->status === 'CLOSED') $this->auditFollowup($followup, $actor, 'DECISION_FOLLOWUP_CLOSED');
            return $locked->fresh(['followup.decision', 'updates', 'responsibleUnit', 'assignedEmployee']);
        });
    }
}
