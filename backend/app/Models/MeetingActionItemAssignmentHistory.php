<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingActionItemAssignmentHistory extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'meeting_action_item_assignment_history';
    protected $fillable = [
        'uuid', 'action_item_id', 'from_unit_id', 'to_unit_id', 'from_employee_id',
        'to_employee_id', 'reason', 'changed_by_user_id', 'changed_at',
    ];
    protected $casts = ['changed_at' => 'datetime'];

    public function actionItem(): BelongsTo { return $this->belongsTo(MeetingActionItem::class, 'action_item_id'); }
    public function fromUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'from_unit_id'); }
    public function toUnit(): BelongsTo { return $this->belongsTo(OrganizationalUnit::class, 'to_unit_id'); }
    public function fromEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'from_employee_id'); }
    public function toEmployee(): BelongsTo { return $this->belongsTo(Employee::class, 'to_employee_id'); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by_user_id'); }
}
