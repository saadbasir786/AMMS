<?php

declare(strict_types=1);

namespace App\Actions\Flow12;

use App\Actions\Flow12\Concerns\Flow12ActionSupport;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordActionItemProgress
{
    use Flow12ActionSupport;

    public function execute(MeetingActionItem $item, User $actor, int $progress, ?string $note = null): MeetingActionItem
    {
        $item->loadMissing('followup.meeting');
        abort_unless($this->governance()->mayWorkItem($item, $actor, 'action-item.progress'), 403, 'UNAUTHORIZED_ACTION_ITEM_UPDATE');
        $progress = $this->assertProgress($progress);
        DB::transaction(function () use ($item, $actor, $progress, $note): void {
            $locked = MeetingActionItem::query()->with('followup.meeting')->lockForUpdate()->findOrFail($item->id);
            abort_unless(in_array($locked->status, [MeetingActionItem::IN_PROGRESS, MeetingActionItem::BLOCKED], true), 409, 'ACTION_ITEM_INVALID_STATE');
            $locked->update(['progress_percent' => $progress, 'status' => $locked->status === MeetingActionItem::BLOCKED ? MeetingActionItem::BLOCKED : MeetingActionItem::IN_PROGRESS, 'lock_version' => $locked->lock_version + 1]);
            $this->updateItem($locked, $actor, MeetingActionItemUpdate::PROGRESS, $progress, $note);
            $this->auditItem($locked, $actor, 'ACTION_ITEM_PROGRESS_RECORDED', $note, ['progress_percent' => $progress]);
        });
        return $item->fresh(['followup.decision', 'updates', 'responsibleUnit', 'assignedEmployee']);
    }
}
