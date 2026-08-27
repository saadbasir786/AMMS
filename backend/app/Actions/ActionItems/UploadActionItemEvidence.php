<?php

declare(strict_types=1);

namespace App\Actions\ActionItems;

use App\Actions\ActionItems\Concerns\Flow12ActionSupport;
use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemEvidence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadActionItemEvidence
{
    use Flow12ActionSupport;

    public function execute(MeetingActionItem $item, User $actor, UploadedFile $file, ?int $updateId = null): MeetingActionItemEvidence
    {
        $item->loadMissing('followup.meeting');
        abort_unless($this->governance()->mayWorkItem($item, $actor, 'action-item.evidence.upload'), 403, 'UNAUTHORIZED_ACTION_ITEM_UPDATE');
        $max = (int) config('amms.action_items.evidence_max_size', 10 * 1024 * 1024);
        abort_unless((int) $file->getSize() <= $max, 422, 'ACTION_ITEM_EVIDENCE_TOO_LARGE');
        $extension = strtolower((string) $file->getClientOriginalExtension());
        abort_unless(in_array($extension, config('amms.action_items.evidence_mimes', []), true), 422, 'ACTION_ITEM_EVIDENCE_MIME_NOT_ALLOWED');
        if ($updateId) abort_unless($item->updates()->whereKey($updateId)->exists(), 422, 'ACTION_ITEM_UPDATE_NOT_FOUND');

        return DB::transaction(function () use ($item, $actor, $file, $updateId): MeetingActionItemEvidence {
            $locked = MeetingActionItem::query()->with('followup.meeting')->lockForUpdate()->findOrFail($item->id);
            abort_unless(!$locked->isTerminal(), 409, 'ACTION_ITEM_INVALID_STATE');
            $uuid = (string) Str::uuid();
            $path = 'flow12/action-items/'.$locked->uuid.'/'.$uuid.'.'.$file->getClientOriginalExtension();
            Storage::disk('local')->putFileAs(dirname($path), $file, basename($path));
            $evidence = MeetingActionItemEvidence::create([
                'uuid' => $uuid,
                'action_item_id' => $locked->id,
                'action_item_update_id' => $updateId,
                'storage_disk' => 'local',
                'storage_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'file_size' => (int) $file->getSize(),
                'uploaded_by_user_id' => $actor->id,
            ]);
            $this->auditItem($locked, $actor, 'ACTION_ITEM_EVIDENCE_UPLOADED', null, ['evidence_id' => $evidence->id, 'mime_type' => $evidence->mime_type, 'file_size' => $evidence->file_size]);
            return $evidence;
        });
    }
}
