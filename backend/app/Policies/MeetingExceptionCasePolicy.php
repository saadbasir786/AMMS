<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Meeting;
use App\Models\MeetingExceptionCase;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;

class MeetingExceptionCasePolicy
{
    public function view(User $user, MeetingExceptionCase $case): bool
    {
        return $case->meeting && app(MeetingExceptionGovernanceResolver::class)->mayView($case->meeting, $user);
    }

    public function viewMeeting(User $user, Meeting $meeting): bool
    {
        return app(MeetingExceptionGovernanceResolver::class)->mayView($meeting, $user);
    }

    public function create(User $user, Meeting $meeting): bool
    {
        return app(MeetingExceptionGovernanceResolver::class)->mayPrepare($meeting, $user);
    }

    public function updateDraft(User $user, MeetingExceptionCase $case): bool
    {
        return app(MeetingExceptionGovernanceResolver::class)->mayUpdateDraft($case, $user);
    }

    public function submit(User $user, MeetingExceptionCase $case): bool
    {
        return app(MeetingExceptionGovernanceResolver::class)->maySubmit($case, $user);
    }

    public function withdraw(User $user, MeetingExceptionCase $case): bool
    {
        return app(MeetingExceptionGovernanceResolver::class)->mayWithdraw($case, $user);
    }

    public function apply(User $user, MeetingExceptionCase $case): bool
    {
        return app(MeetingExceptionGovernanceResolver::class)->mayApply($case, $user);
    }
}
