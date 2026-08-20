<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MeetingExceptionReview;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;

class MeetingExceptionReviewPolicy
{
    public function view(User $user, MeetingExceptionReview $review): bool
    {
        $meeting = $review->version?->exceptionCase?->meeting;
        return $meeting && app(MeetingExceptionGovernanceResolver::class)->mayView($meeting, $user);
    }

    public function acknowledge(User $user, MeetingExceptionReview $review): bool
    {
        return app(MeetingExceptionGovernanceResolver::class)->mayAcknowledge($review, $user);
    }

    public function approve(User $user, MeetingExceptionReview $review): bool
    {
        return app(MeetingExceptionGovernanceResolver::class)->mayApprove($review, $user);
    }
}
