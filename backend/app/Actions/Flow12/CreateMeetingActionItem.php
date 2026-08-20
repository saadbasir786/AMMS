<?php

declare(strict_types=1);

namespace App\Actions\Flow12;

use App\Actions\Flow12\Concerns\Flow12ActionSupport;
use App\Models\MeetingActionItem;
use App\Models\MeetingDecisionFollowUp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateMeetingActionItem
{
    use Flow12ActionSupport;

    public function execute(MeetingDecisionFollowUp $followup, User $actor, array $attributes): MeetingActionItem
    {
        $followup->loadMissing(['meeting', 'decision', 'actionItems']);
        abort_unless($this->governance()->mayCreateActionItem($followup, $actor), 403, 'UNAUTHORIZED_ACTION_ITEM_CREATE');
        $meeting = $followup->meeting;
        [$minutes, $version] = $this->governance()->source($meeting);
        abort_unless((int) $followup->meeting_minute_version_id === (int) $version->id, 422, 'FOLLOWUP_SOURCE_VERSION_MISMATCH');
        abort_unless($followup->status !== MeetingDecisionFollowUp::NO_ACTION_REQUIRED, 422, 'DECISION_NO_ACTION_REQUIRED');

        return DB::transaction(function () use ($followup, $actor, $attributes, $meeting): MeetingActionItem {
            $lockedFollowup = MeetingDecisionFollowUp::query()->with(['meeting', 'decision'])->lockForUpdate()->findOrFail($followup->id);
            abort_unless($lockedFollowup->status !== MeetingDecisionFollowUp::NO_ACTION_REQUIRED, 422, 'DECISION_NO_ACTION_REQUIRED');
            $unitId = (int) ($attributes['responsible_unit_id'] ?? 0);
            abort_unless($unitId > 0, 422, 'ACTION_ITEM_RESPONSIBLE_UNIT_REQUIRED');
            $this->governance()->validateUnit($unitId, $lockedFollowup->meeting);
            $employeeId = isset($attributes['assigned_employee_id']) && $attributes['assigned_employee_id'] !== '' ? (int) $attributes['assigned_employee_id'] : null;
            $this->governance()->validateEmployee($employeeId, $unitId, $lockedFollowup->meeting);
            $sequence = $this->governance()->nextSequence($lockedFollowup);
            $item = MeetingActionItem::create([
                'uuid' => (string) Str::uuid(),
                'decision_followup_id' => $lockedFollowup->id,
                'sequence_number' => $sequence,
                'title' => trim((string) ($attributes['title'] ?? '')),
                'description' => $attributes['description'] ?? null,
                'responsible_unit_id' => $unitId,
                'assigned_employee_id' => $employeeId,
                'status' => MeetingActionItem::OPEN,
                'progress_percent' => 0,
                'due_date' => $attributes['due_date'] ?? $lockedFollowup->decision?->target_date,
                'created_by_user_id' => $actor->id,
                'lock_version' => 0,
            ]);
            abort_unless($item->title !== '', 422, 'ACTION_ITEM_TITLE_REQUIRED');
            $this->auditItem($item, $actor, 'ACTION_ITEM_CREATED', null, ['action_number' => $item->action_number]);
            $this->notifyUnit($item, 'ACTION_ITEM_CREATED', 'اقدام جدید ثبت شد', 'یک اقدام عملیاتی جدید در حوزهٔ ریاست شما ثبت شده است.', ['action_item_id' => $item->id]);
            $this->notifyUser($employeeId ? \App\Models\User::query()->where('employee_id', $employeeId)->value('id') : null, 'ACTION_ITEM_ASSIGNED', 'اقدام به شما سپرده شد', 'یک اقدام عملیاتی جدید به شما سپرده شده است.', ['action_item_id' => $item->id]);
            $lockedFollowup->update(['status' => MeetingDecisionFollowUp::ACTIVE]);
            return $item->fresh(['followup.decision', 'responsibleUnit', 'assignedEmployee']);
        });
    }
}
