<?php

declare(strict_types=1);

namespace App\Actions\ActionItems;

use App\Actions\ActionItems\Concerns\Flow12ActionSupport;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemExtensionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestActionItemDueDateExtension
{
    use Flow12ActionSupport;

    public function execute(MeetingActionItem $item, User $actor, string $requestedDate, string $reason): MeetingActionItemExtensionRequest
    {
        $item->loadMissing('followup.meeting');
        abort_unless($this->governance()->mayWorkItem($item, $actor, 'action-item.extension.request'), 403, 'UNAUTHORIZED_ACTION_ITEM_UPDATE');
        $reason = $this->assertReason($reason, 'ACTION_ITEM_EXTENSION_REASON_REQUIRED');
        abort_unless($item->due_date, 422, 'ACTION_ITEM_EXTENSION_CURRENT_DATE_REQUIRED');
        abort_unless($requestedDate > $item->due_date->toDateString(), 422, 'ACTION_ITEM_EXTENSION_INVALID_DATE');

        return DB::transaction(function () use ($item, $actor, $requestedDate, $reason): MeetingActionItemExtensionRequest {
            $locked = MeetingActionItem::query()->with('followup.meeting')->lockForUpdate()->findOrFail($item->id);
            abort_unless(!$locked->isTerminal(), 409, 'ACTION_ITEM_INVALID_STATE');
            abort_unless(!$locked->extensionRequests()->where('status', MeetingActionItemExtensionRequest::PENDING)->exists(), 409, 'ACTION_ITEM_EXTENSION_ALREADY_PENDING');
            $extension = MeetingActionItemExtensionRequest::create([
                'uuid' => (string) Str::uuid(),
                'action_item_id' => $locked->id,
                'current_due_date' => $locked->due_date,
                'requested_due_date' => $requestedDate,
                'reason' => $reason,
                'status' => MeetingActionItemExtensionRequest::PENDING,
                'pending_marker' => 1,
                'requested_by_user_id' => $actor->id,
                'requested_at' => now(),
            ]);
            $this->auditItem($locked, $actor, 'ACTION_ITEM_EXTENSION_REQUESTED', $reason, ['extension_request_id' => $extension->id, 'requested_due_date' => $requestedDate]);
            $this->notifyOwners($locked->followup, 'ACTION_ITEM_EXTENSION_REQUESTED', 'درخواست تمدید مهلت', 'برای یک اقدام عملیاتی درخواست تمدید مهلت ثبت شده است.', ['action_item_id' => $locked->id, 'extension_request_id' => $extension->id]);
            return $extension->fresh(['actionItem']);
        });
    }
}
