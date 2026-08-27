<?php

declare(strict_types=1);

namespace App\Actions\ActionItems;

use App\Actions\ActionItems\Concerns\Flow12ActionSupport;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnblockActionItem
{
    use Flow12ActionSupport;

    public function execute(MeetingActionItem $item, User $actor, string $note): MeetingActionItem
    {
        $item->loadMissing('followup.meeting');
        abort_unless($this->governance()->mayWorkItem($item, $actor, 'action-item.progress'), 403, 'UNAUTHORIZED_ACTION_ITEM_UPDATE');
        $note = $this->assertReason($note, 'ACTION_ITEM_UNBLOCK_NOTE_REQUIRED');
        DB::transaction(function () use ($item, $actor, $note): void {
            $locked = MeetingActionItem::query()->with('followup.meeting')->lockForUpdate()->findOrFail($item->id);
            abort_unless($locked->status === MeetingActionItem::BLOCKED, 409, 'ACTION_ITEM_INVALID_STATE');
            $locked->update(['status' => MeetingActionItem::IN_PROGRESS, 'lock_version' => $locked->lock_version + 1]);
            $this->updateItem($locked, $actor, MeetingActionItemUpdate::UNBLOCKED, (int) $locked->progress_percent, $note);
            $this->auditItem($locked, $actor, 'ACTION_ITEM_UNBLOCKED', $note);
        });
        return $item->fresh(['followup.decision', 'updates', 'responsibleUnit', 'assignedEmployee']);
    }
}
