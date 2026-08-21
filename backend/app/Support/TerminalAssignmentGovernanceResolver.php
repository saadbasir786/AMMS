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

        // SYSTEM_ADMIN may inspect infrastructure state, but may not perform meeting-governed
        // assignment/refresh/revoke operations (see isGovernedActor()).
        if ($actor->hasScopedRole('SYSTEM_ADMIN')) return true;

        $governance = $this->resolve($meeting);
        if ($governance['authority_controlled']) {
            return $governance['authority_office_unit_id'] > 0
                && ($actor->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id'])
                    || $actor->hasScopedRole('AUTHORITY_OFFICE', $governance['authority_office_unit_id']));
        }

        foreach ($governance['required_unit_ids'] as $unitId) {
            if ($actor->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $unitId)
                || $actor->hasScopedRole('DIRECTOR', (int) $unitId)) return true;
        }
        return false;
    }

    private function isGovernedActor(Meeting $meeting, User $actor): bool
    {
        if ($actor->hasScopedRole('SYSTEM_ADMIN')) return false;

        $governance = $this->resolve($meeting);
        if ($governance['authority_controlled']) {
            return $governance['authority_office_unit_id'] > 0
                && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']);
        }

        $leadUnitId = (int) ($meeting->units()->where('role', 'REQUESTING')->value('organizational_unit_id')
            ?: ($governance['required_unit_ids'][0] ?? 0));
        return $leadUnitId > 0 && $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $leadUnitId);
    }
}
