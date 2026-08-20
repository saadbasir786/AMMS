<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingActionItemExtensionRequest extends \Illuminate\Database\Eloquent\Model
{
    public const PENDING = 'PENDING';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';
    public const WITHDRAWN = 'WITHDRAWN';

    protected $table = 'meeting_action_item_extension_requests';
    protected $fillable = [
        'uuid', 'action_item_id', 'current_due_date', 'requested_due_date', 'reason',
        'status', 'pending_marker', 'requested_by_user_id', 'requested_at',
        'reviewed_by_user_id', 'reviewed_at', 'review_reason',
    ];
    protected $casts = [
        'current_due_date' => 'date',
        'requested_due_date' => 'date',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function actionItem(): BelongsTo { return $this->belongsTo(MeetingActionItem::class, 'action_item_id'); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by_user_id'); }
}
