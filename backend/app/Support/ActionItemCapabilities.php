<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\MeetingActionItem;
use App\Models\User;

/**
 * Projects action-item policy decisions into a frontend-safe capability contract.
 * The policy and resolver remain authoritative for every mutation.
 */
class ActionItemCapabilities
{
    public function __construct(private readonly DecisionFollowUpGovernanceResolver $governance) {}

    public function resolve(MeetingActionItem $item, User $actor): array
    {
        $item->loadMissing('followup.meeting');

        return [
            'view' => $this->governance->mayViewActionItem($item, $actor),
            'start' => $this->governance->mayWorkItem($item, $actor, 'action-item.start'),
            'progress' => $this->governance->mayWorkItem($item, $actor, 'action-item.progress'),
            'block' => $this->governance->mayWorkItem($item, $actor, 'action-item.block'),
            'submit_completion' => $this->governance->mayWorkItem($item, $actor, 'action-item.submit-completion'),
            'verify_completion' => $this->governance->mayVerify($item, $actor),
            'assignment' => $this->governance->mayChangeEmployee($item, $actor),
            'change_unit' => $this->governance->mayChangeUnit($item, $actor),
            'extension_request' => $this->governance->mayWorkItem($item, $actor, 'action-item.extension.request'),
            'extension_review' => $this->governance->mayReviewExtension($item, $actor),
            'cancel' => $this->governance->mayCancel($item, $actor),
            'evidence_view' => $this->governance->mayViewActionItem($item, $actor) && $actor->can('action-item.evidence.view'),
            'evidence_upload' => $this->governance->mayWorkItem($item, $actor, 'action-item.evidence.upload'),
        ];
    }
}
