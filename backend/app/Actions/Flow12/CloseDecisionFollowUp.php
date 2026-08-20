<?php

declare(strict_types=1);

namespace App\Actions\Flow12;

use App\Actions\Flow12\Concerns\Flow12ActionSupport;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingActionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CloseDecisionFollowUp
{
    use Flow12ActionSupport;

    public function execute(MeetingDecisionFollowUp $followup, User $actor): MeetingDecisionFollowUp
    {
        $followup->loadMissing('meeting');
        abort_unless($this->governance()->mayClassify($followup, $actor), 403, 'UNAUTHORIZED_DECISION_FOLLOWUP');
        return DB::transaction(function () use ($followup, $actor): MeetingDecisionFollowUp {
            $locked = MeetingDecisionFollowUp::query()->with(['meeting', 'actionItems'])->lockForUpdate()->findOrFail($followup->id);
            $eligible = $locked->status === MeetingDecisionFollowUp::NO_ACTION_REQUIRED
                || ($locked->actionItems->isNotEmpty() && $locked->actionItems->every(fn (MeetingActionItem $item): bool => in_array($item->status, [MeetingActionItem::COMPLETED, MeetingActionItem::CANCELLED], true)));
            abort_unless($eligible, 422, 'DECISION_FOLLOWUP_NOT_READY_TO_CLOSE');
            $locked->update(['status' => MeetingDecisionFollowUp::CLOSED]);
            $this->auditFollowup($locked, $actor, 'DECISION_FOLLOWUP_CLOSED');
            return $locked->fresh(['decision', 'actionItems']);
        });
    }
}
