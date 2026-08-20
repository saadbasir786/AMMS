<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingActionItemUpdate extends \Illuminate\Database\Eloquent\Model
{
    public const PROGRESS = 'PROGRESS';
    public const BLOCKED = 'BLOCKED';
    public const UNBLOCKED = 'UNBLOCKED';
    public const COMPLETION_SUBMITTED = 'COMPLETION_SUBMITTED';
    public const COMPLETION_RETURNED = 'COMPLETION_RETURNED';
    public const COMMENT = 'COMMENT';

    protected $table = 'meeting_action_item_updates';
    protected $fillable = ['uuid', 'action_item_id', 'update_type', 'progress_percent', 'note', 'created_by_user_id'];
    protected $casts = ['progress_percent' => 'integer'];

    public function actionItem(): BelongsTo { return $this->belongsTo(MeetingActionItem::class, 'action_item_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function evidence(): HasMany { return $this->hasMany(MeetingActionItemEvidence::class, 'action_item_update_id'); }
}
