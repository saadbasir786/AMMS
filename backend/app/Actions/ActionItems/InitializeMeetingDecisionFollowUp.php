<?php

declare(strict_types=1);

namespace App\Actions\ActionItems;

use App\Actions\ActionItems\Concerns\ActionItemWorkflowSupport;
use App\Models\Meeting;
use App\Models\MeetingDecisionFollowUp;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InitializeMeetingDecisionFollowUp
{
    use ActionItemWorkflowSupport;

    public function execute(Meeting $meeting, User $actor): Collection
    {
        abort_unless($this->governance()->mayInitialize($meeting, $actor), 403, 'UNAUTHORIZED_DECISION_FOLLOWUP');
        $ids = DB::transaction(function () use ($meeting, $actor): array {
            $locked = Meeting::query()->with('minutes')->lockForUpdate()->findOrFail($meeting->id);
            [$minutes, $version] = $this->governance()->source($locked);
            $ids = [];
            $decisions = $version->decisions()->orderBy('sequence_number')->get();
            foreach ($decisions as $decision) {
                $followup = MeetingDecisionFollowUp::query()->where('meeting_decision_id', $decision->id)->lockForUpdate()->first();
                if (!$followup) {
                    $followup = MeetingDecisionFollowUp::create([
                        'uuid' => (string) Str::uuid(),
                        'meeting_id' => $locked->id,
                        'meeting_minutes_id' => $minutes->id,
                        'meeting_minute_version_id' => $version->id,
                        'meeting_decision_id' => $decision->id,
                        'decision_number' => $decision->decision_number,
                        'classification' => MeetingDecisionFollowUp::UNCLASSIFIED,
                        'status' => MeetingDecisionFollowUp::OPEN,
                    ]);
                    $this->auditFollowup($followup, $actor, 'DECISION_FOLLOWUP_INITIALIZED', null, ['decision_id' => $decision->id]);
                }
                $ids[] = $followup->id;
            }
            return $ids;
        });

        return MeetingDecisionFollowUp::query()->with(['decision', 'actionItems'])->whereIn('id', $ids)->orderBy('id')->get();
    }
}
