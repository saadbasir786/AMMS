<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\User;

class TerminalAssignmentGovernanceResolver
{
    public function resolve(Meeting $meeting): array
    {
        $schedule = app(ScheduleGovernanceResolver::class)->resolve($meeting);
        return [
            ...$schedule,
            'assignment_mode' => $schedule['mode'],
        ];
    }

    public function mayAssign(Meeting $meeting, User $actor): bool
    {
        if (!$actor->isActive() || !$actor->can('meeting-terminal.assign')) return false;
        return $this->isGovernedActor($meeting, $actor);
    }

    public function mayRefresh(Meeting $meeting, User $actor): bool
    {
        return $actor->isActive() && $actor->can('meeting-terminal.refresh') && $this->isGovernedActor($meeting, $actor);
    }

    public function mayRevoke(Meeting $meeting, User $actor): bool
    {
        return $actor->isActive() && $actor->can('meeting-terminal.revoke') && $this->isGovernedActor($meeting, $actor);
    }

    public function mayView(Meeting $meeting, User $actor): bool
    {
        if (!$actor->isActive() || !$actor->can('meeting-terminal.view')) return false;
        if ($actor->id === $meeting->coordinator_id) return true;
        if ($actor->hasScopedRole('SYSTEM_ADMIN')) return true;
        $governance = $this->resolve($meeting);
        if ($governance['authority_controlled']) {
            return $governance['authority_office_unit_id'] > 0
                && ($actor->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id'])
                    || $actor->hasScopedRole('AUTHORITY_OFFICE', $governance['authority_office_unit_id']));
        }
        return $actor->hasScopedRole('MEETING_COORDINATOR', $meeting->organizing_unit_id)
            || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $meeting->organizing_unit_id)
            || $actor->hasScopedRole('DIRECTOR', $meeting->organizing_unit_id);
    }

    private function isGovernedActor(Meeting $meeting, User $actor): bool
    {
        if ($actor->id === $meeting->coordinator_id) return true;
        if ($actor->hasScopedRole('SYSTEM_ADMIN')) return false;
        $governance = $this->resolve($meeting);
        if ($governance['authority_controlled']) {
            return $governance['authority_office_unit_id'] > 0
                && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']);
        }
        return $actor->hasScopedRole('MEETING_COORDINATOR', $meeting->organizing_unit_id);
    }
}
