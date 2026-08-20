<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingActionItemEvidence extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'meeting_action_item_evidence';
    protected $fillable = [
        'uuid', 'action_item_id', 'action_item_update_id', 'storage_disk', 'storage_path',
        'original_filename', 'mime_type', 'file_size', 'uploaded_by_user_id',
    ];
    protected $casts = ['file_size' => 'integer'];

    public function actionItem(): BelongsTo { return $this->belongsTo(MeetingActionItem::class, 'action_item_id'); }
    public function actionItemUpdate(): BelongsTo { return $this->belongsTo(MeetingActionItemUpdate::class, 'action_item_update_id'); }
    public function uploadedBy(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }
}
