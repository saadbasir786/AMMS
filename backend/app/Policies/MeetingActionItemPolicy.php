<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MeetingActionItem;
use App\Models\MeetingActionItemEvidence;
use App\Models\MeetingActionItemExtensionRequest;
use App\Models\User;
use App\Support\DecisionFollowUpGovernanceResolver;

class MeetingActionItemPolicy
{
    private function resolver(): DecisionFollowUpGovernanceResolver { return app(DecisionFollowUpGovernanceResolver::class); }

    public function view(User $user, MeetingActionItem $item): bool
    {
        return $this->resolver()->mayViewActionItem($item, $user);
    }

    public function start(User $user, MeetingActionItem $item): bool
    {
        return $this->resolver()->mayWorkItem($item, $user, 'action-item.start');
    }

    public function progress(User $user, MeetingActionItem $item): bool
    {
        return $this->resolver()->mayWorkItem($item, $user, 'action-item.progress');
    }

    public function block(User $user, MeetingActionItem $item): bool
    {
        return $this->resolver()->mayWorkItem($item, $user, 'action-item.block');
    }

    public function submitCompletion(User $user, MeetingActionItem $item): bool
    {
        return $this->resolver()->mayWorkItem($item, $user, 'action-item.submit-completion');
    }

    public function verifyCompletion(User $user, MeetingActionItem $item): bool
    {
        return $this->resolver()->mayVerify($item, $user);
    }

    public function assignment(User $user, MeetingActionItem $item): bool
    {
        return $this->resolver()->mayChangeEmployee($item, $user);
    }

    public function changeUnit(User $user, MeetingActionItem $item): bool
    {
        return $this->resolver()->mayChangeUnit($item, $user);
    }

    public function extensionRequest(User $user, MeetingActionItem $item): bool
    {
        return $this->resolver()->mayWorkItem($item, $user, 'action-item.extension.request');
    }

    public function extensionReview(User $user, MeetingActionItemExtensionRequest $extension): bool
    {
        return $extension->actionItem && $this->resolver()->mayReviewExtension($extension->actionItem, $user);
    }

    public function cancel(User $user, MeetingActionItem $item): bool
    {
        return $this->resolver()->mayCancel($item, $user);
    }

    public function evidenceView(User $user, MeetingActionItemEvidence $evidence): bool
    {
        return $evidence->actionItem && $this->resolver()->mayViewActionItem($evidence->actionItem, $user) && $user->can('action-item.evidence.view');
    }

    public function evidenceUpload(User $user, MeetingActionItem $item): bool
    {
        return $this->resolver()->mayWorkItem($item, $user, 'action-item.evidence.upload');
    }
}
