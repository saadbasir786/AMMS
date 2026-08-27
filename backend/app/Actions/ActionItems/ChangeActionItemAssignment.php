<?php

declare(strict_types=1);

namespace App\Actions\ActionItems;

use App\Actions\ActionItems\Concerns\ActionItemWorkflowSupport;
use App\Models\MeetingActionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ChangeActionItemAssignment
{
    use ActionItemWorkflowSupport;

    public function execute(MeetingActionItem $item, User $actor, ?int $responsibleUnitId, ?int $employeeId, string $reason): MeetingActionItem
    {
        $item->loadMissing('followup.meeting');
        $reason = $this->assertReason($reason, 'ACTION_ITEM_ASSIGNMENT_REASON_REQUIRED');
        $unitId = $responsibleUnitId ?: (int) $item->responsible_unit_id;
        $isUnitChange = $unitId !== (int) $item->responsible_unit_id;
        abort_unless($isUnitChange ? $this->governance()->mayChangeUnit($item, $actor) : $this->governance()->mayChangeEmployee($item, $actor), 403, $isUnitChange ? 'UNAUTHORIZED_ACTION_ITEM_UNIT_CHANGE' : 'UNAUTHORIZED_ACTION_ITEM_REASSIGNMENT');
        $this->governance()->validateUnit($unitId, $item->followup->meeting);
        $this->governance()->validateEmployee($employeeId, $unitId, $item->followup->meeting);

        DB::transaction(function () use ($item, $actor, $unitId, $employeeId, $reason): void {
            $locked = MeetingActionItem::query()->with('followup.meeting')->lockForUpdate()->findOrFail($item->id);
            abort_unless(in_array($locked->status, [MeetingActionItem::OPEN, MeetingActionItem::IN_PROGRESS, MeetingActionItem::BLOCKED], true), 409, 'ACTION_ITEM_INVALID_STATE');
            abort_unless((int) $locked->responsible_unit_id !== $unitId || (int) $locked->assigned_employee_id !== (int) $employeeId, 422, 'ACTION_ITEM_ASSIGNMENT_UNCHANGED');
            $this->recordAssignment($locked, $actor, $unitId, $employeeId, $reason);
            $locked->update(['responsible_unit_id' => $unitId, 'assigned_employee_id' => $employeeId, 'lock_version' => $locked->lock_version + 1]);
            $this->auditItem($locked, $actor, 'ACTION_ITEM_ASSIGNMENT_CHANGED', $reason, ['responsible_unit_id' => $unitId, 'assigned_employee_id' => $employeeId]);
            $this->notifyUser($employeeId ? User::query()->where('employee_id', $employeeId)->value('id') : null, 'ACTION_ITEM_ASSIGNED', 'اقدام به شما سپرده شد', 'مسئولیت یک اقدام عملیاتی به شما واگذار شد.', ['action_item_id' => $locked->id]);
        });
        return $item->fresh(['followup.decision', 'responsibleUnit', 'assignedEmployee', 'assignmentHistory']);
    }
}
