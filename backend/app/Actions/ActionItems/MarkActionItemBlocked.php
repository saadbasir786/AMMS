<?php

declare(strict_types=1);

namespace App\Actions\ActionItems;

use App\Actions\ActionItems\Concerns\Flow12ActionSupport;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MarkActionItemBlocked
{
    use Flow12ActionSupport;

    public function execute(MeetingActionItem $item, User $actor, string $reason): MeetingActionItem
    {
        $item->loadMissing('followup.meeting');
        abort_unless($this->governance()->mayWorkItem($item, $actor, 'action-item.block'), 403, 'UNAUTHORIZED_ACTION_ITEM_UPDATE');
        $reason = $this->assertReason($reason, 'ACTION_ITEM_BLOCK_REASON_REQUIRED');
        DB::transaction(function () use ($item, $actor, $reason): void {
            $locked = MeetingActionItem::query()->with('followup.meeting')->lockForUpdate()->findOrFail($item->id);
            abort_unless($locked->status === MeetingActionItem::IN_PROGRESS, 409, 'ACTION_ITEM_INVALID_STATE');
            $locked->update(['status' => MeetingActionItem::BLOCKED, 'lock_version' => $locked->lock_version + 1]);
            $this->updateItem($locked, $actor, MeetingActionItemUpdate::BLOCKED, (int) $locked->progress_percent, $reason);
            $this->auditItem($locked, $actor, 'ACTION_ITEM_BLOCKED', $reason);
        });
        return $item->fresh(['followup.decision', 'updates', 'responsibleUnit', 'assignedEmployee']);
    }
}
