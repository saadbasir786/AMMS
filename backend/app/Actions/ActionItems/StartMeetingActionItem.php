<?php

declare(strict_types=1);

namespace App\Actions\ActionItems;

use App\Actions\ActionItems\Concerns\ActionItemWorkflowSupport;
use App\Models\MeetingActionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StartMeetingActionItem
{
    use ActionItemWorkflowSupport;

    public function execute(MeetingActionItem $item, User $actor): MeetingActionItem
    {
        $item->loadMissing('followup.meeting');
        abort_unless($this->governance()->mayWorkItem($item, $actor, 'action-item.start'), 403, 'UNAUTHORIZED_ACTION_ITEM_UPDATE');
        DB::transaction(function () use ($item, $actor): void {
            $locked = MeetingActionItem::query()->with('followup.meeting')->lockForUpdate()->findOrFail($item->id);
            abort_unless($locked->status === MeetingActionItem::OPEN, 409, 'ACTION_ITEM_ALREADY_STARTED');
            $locked->update(['status' => MeetingActionItem::IN_PROGRESS, 'started_at' => now(), 'lock_version' => $locked->lock_version + 1]);
            $this->updateItem($locked, $actor, 'PROGRESS', (int) $locked->progress_percent, 'کار آغاز شد.');
            $this->auditItem($locked, $actor, 'ACTION_ITEM_STARTED');
        });
        return $item->fresh(['followup.decision', 'updates', 'responsibleUnit', 'assignedEmployee']);
    }
}
