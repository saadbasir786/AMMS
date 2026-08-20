<?php

declare(strict_types=1);

namespace App\Actions\Flow12;

use App\Actions\Flow12\Concerns\Flow12ActionSupport;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReturnActionItemCompletion
{
    use Flow12ActionSupport;

    public function execute(MeetingActionItem $item, User $actor, string $reason): MeetingActionItem
    {
        $item->loadMissing('followup.meeting');
        abort_unless($this->governance()->mayVerify($item, $actor), 403, 'UNAUTHORIZED_ACTION_ITEM_VERIFICATION');
        $reason = $this->assertReason($reason, 'ACTION_ITEM_COMPLETION_RETURN_REASON_REQUIRED');
        return DB::transaction(function () use ($item, $actor, $reason): MeetingActionItem {
            $locked = MeetingActionItem::query()->with('followup')->lockForUpdate()->findOrFail($item->id);
            abort_unless($locked->status === MeetingActionItem::PENDING_VERIFICATION, 409, 'ACTION_ITEM_NOT_PENDING_VERIFICATION');
            $locked->update(['status' => MeetingActionItem::IN_PROGRESS, 'lock_version' => $locked->lock_version + 1]);
            $this->updateItem($locked, $actor, MeetingActionItemUpdate::COMPLETION_RETURNED, (int) $locked->progress_percent, $reason);
            $this->auditItem($locked, $actor, 'ACTION_ITEM_COMPLETION_RETURNED', $reason);
            $this->notifyUser($locked->submitted_by_user_id, 'ACTION_ITEM_COMPLETION_RETURNED', 'تکمیل اقدام برای اصلاح برگشت داده شد', 'تکمیل اقدام عملیاتی برای اصلاحات بیشتر برگشت داده شد.', ['action_item_id' => $locked->id]);
            return $locked->fresh(['followup.decision', 'updates', 'evidence', 'responsibleUnit', 'assignedEmployee']);
        });
    }
}
