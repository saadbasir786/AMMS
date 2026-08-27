<?php

declare(strict_types=1);

namespace App\Actions\ActionItems;

use App\Actions\ActionItems\Concerns\ActionItemWorkflowSupport;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemEvidence;
use App\Models\MeetingActionItemUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitActionItemCompletion
{
    use ActionItemWorkflowSupport;

    public function execute(MeetingActionItem $item, User $actor, string $note, array $evidence = []): MeetingActionItem
    {
        $item->loadMissing('followup.meeting');
        abort_unless($this->governance()->mayWorkItem($item, $actor, 'action-item.submit-completion'), 403, 'UNAUTHORIZED_ACTION_ITEM_UPDATE');
        $note = $this->assertReason($note, 'ACTION_ITEM_COMPLETION_NOTE_REQUIRED');

        return DB::transaction(function () use ($item, $actor, $note, $evidence): MeetingActionItem {
            $locked = MeetingActionItem::query()->with('followup.meeting')->lockForUpdate()->findOrFail($item->id);
            abort_unless($this->governance()->mayWorkItem($locked, $actor, 'action-item.submit-completion'), 403, 'UNAUTHORIZED_ACTION_ITEM_UPDATE');
            abort_unless(in_array($locked->status, [MeetingActionItem::IN_PROGRESS, MeetingActionItem::BLOCKED], true), 409, 'ACTION_ITEM_INVALID_STATE');
            abort_unless((int) $locked->progress_percent === 100, 422, 'ACTION_ITEM_COMPLETION_PROGRESS_REQUIRED');
            $locked->update(['status' => MeetingActionItem::PENDING_VERIFICATION, 'submitted_for_verification_at' => now(), 'submitted_by_user_id' => $actor->id, 'lock_version' => $locked->lock_version + 1]);
            $update = $this->updateItem($locked, $actor, MeetingActionItemUpdate::COMPLETION_SUBMITTED, 100, $note);
            foreach ($evidence as $meta) {
                if (is_array($meta) && !empty($meta['storage_path'])) {
                    $locked->evidence()->create([
                        'uuid' => (string) Str::uuid(), 'action_item_update_id' => $update->id,
                        'storage_disk' => $meta['storage_disk'] ?? 'local_private', 'storage_path' => $meta['storage_path'],
                        'original_filename' => $meta['original_filename'] ?? 'evidence', 'mime_type' => $meta['mime_type'] ?? 'application/octet-stream',
                        'file_size' => (int) ($meta['file_size'] ?? 0), 'uploaded_by_user_id' => $actor->id,
                    ]);
                }
            }
            $this->auditItem($locked, $actor, 'ACTION_ITEM_COMPLETION_SUBMITTED', $note, ['evidence_count' => count($evidence)]);
            $this->notifyOwners($locked->followup, 'ACTION_ITEM_COMPLETION_SUBMITTED', 'تکمیل اقدام برای تأیید ارسال شد', 'یک اقدام عملیاتی برای تأیید نهایی ارسال شده است.', ['action_item_id' => $locked->id]);
            return $locked->fresh(['followup.decision', 'responsibleUnit', 'assignedEmployee']);
        });
    }
}
