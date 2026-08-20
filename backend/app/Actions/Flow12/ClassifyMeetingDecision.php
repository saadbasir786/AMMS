<?php

declare(strict_types=1);

namespace App\Actions\Flow12;

use App\Actions\Flow12\Concerns\Flow12ActionSupport;
use App\Models\MeetingDecisionFollowUp;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClassifyMeetingDecision
{
    use Flow12ActionSupport;

    public function execute(MeetingDecisionFollowUp $followup, User $actor, string $classification, ?string $reason = null): MeetingDecisionFollowUp
    {
        abort_unless($this->governance()->mayClassify($followup, $actor), 403, 'UNAUTHORIZED_DECISION_FOLLOWUP');
        $classification = strtoupper(trim($classification));
        abort_unless(in_array($classification, [MeetingDecisionFollowUp::NO_ACTION_REQUIRED, MeetingDecisionFollowUp::ACTION_REQUIRED], true), 422, 'DECISION_CLASSIFICATION_INVALID');
        $reason = $classification === MeetingDecisionFollowUp::NO_ACTION_REQUIRED ? $this->assertReason($reason, 'DECISION_NO_ACTION_REASON_REQUIRED') : null;

        DB::transaction(function () use ($followup, $actor, $classification, $reason): void {
            $locked = MeetingDecisionFollowUp::query()->with('meeting')->lockForUpdate()->findOrFail($followup->id);
            abort_unless($locked->status !== MeetingDecisionFollowUp::CLOSED, 422, 'DECISION_FOLLOWUP_ALREADY_CLOSED');
            abort_unless($locked->classification === MeetingDecisionFollowUp::UNCLASSIFIED || ($locked->classification === $classification && $locked->status !== MeetingDecisionFollowUp::CLOSED), 422, 'DECISION_ALREADY_CLASSIFIED');
            $status = $classification === MeetingDecisionFollowUp::NO_ACTION_REQUIRED ? MeetingDecisionFollowUp::CLOSED : ($locked->actionItems()->exists() ? MeetingDecisionFollowUp::ACTIVE : MeetingDecisionFollowUp::OPEN);
            $locked->update(['classification' => $classification, 'status' => $status, 'classified_by_user_id' => $actor->id, 'classified_at' => now(), 'no_action_reason' => $reason]);
            $this->auditFollowup($locked, $actor, $classification === MeetingDecisionFollowUp::NO_ACTION_REQUIRED ? 'DECISION_CLASSIFIED_NO_ACTION_REQUIRED' : 'DECISION_CLASSIFIED_ACTION_REQUIRED', $reason);
        });

        return $followup->fresh(['decision', 'actionItems']);
    }
}
