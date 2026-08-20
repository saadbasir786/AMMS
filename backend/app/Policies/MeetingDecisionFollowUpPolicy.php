<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MeetingDecisionFollowUp;
use App\Models\User;
use App\Support\DecisionFollowUpGovernanceResolver;

class MeetingDecisionFollowUpPolicy
{
    public function view(User $user, MeetingDecisionFollowUp $followup): bool
    {
        $meeting = $followup->meeting;
        return $meeting && $user->isActive() && $user->can('decision.followup.view')
            && app(MeetingPolicy::class)->view($user, $meeting);
    }

    public function initialize(User $user, MeetingDecisionFollowUp $followup): bool
    {
        return $followup->meeting && app(DecisionFollowUpGovernanceResolver::class)->mayInitialize($followup->meeting, $user);
    }

    public function classify(User $user, MeetingDecisionFollowUp $followup): bool
    {
        return app(DecisionFollowUpGovernanceResolver::class)->mayClassify($followup, $user);
    }

    public function createActionItem(User $user, MeetingDecisionFollowUp $followup): bool
    {
        return app(DecisionFollowUpGovernanceResolver::class)->mayCreateActionItem($followup, $user);
    }

    public function close(User $user, MeetingDecisionFollowUp $followup): bool
    {
        return app(DecisionFollowUpGovernanceResolver::class)->mayClassify($followup, $user);
    }
}
