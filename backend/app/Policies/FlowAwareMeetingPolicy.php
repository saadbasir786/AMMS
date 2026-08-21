<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;
use App\Support\ScheduleGovernanceResolver;

class FlowAwareMeetingPolicy extends MeetingPolicy
{
    public function flow07CreateCase(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.create')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);

        if ($governance['mode'] === 'AUTHORITY_CONTROLLED' && $governance['authority_office_unit_id'] > 0
            && $user->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id'])) {
            return true;
        }

        return $this->hasFormalRole($user, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT', 'DIRECTOR']);
    }

    public function flow07PeerPropose(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.propose')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);
        if (!in_array($governance['mode'], ['INTERNAL_SINGLE_UNIT', 'PEER_MULTI_UNIT'], true)) return false;

        return $this->hasFormalRole($user, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT']);
    }

    public function flow07AuthorityPropose(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.propose')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);
        return $governance['mode'] === 'AUTHORITY_CONTROLLED'
            && $governance['authority_office_unit_id'] > 0
            && $user->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']);
    }

    public function flow07Acknowledge(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.acknowledge')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);
        return $this->hasFormalRole($user, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT', 'DIRECTOR']);
    }

    public function flow07RequestAlternative(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.request-alternative')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);
        return $this->hasFormalRole($user, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT', 'DIRECTOR']);
    }

    public function flow07ResolveAlternative(User $user, Meeting $meeting): bool
    {
        return $this->flow07AuthorityPropose($user, $meeting) && $user->can('meeting-reschedule.request-alternative');
    }

    public function flow07Confirm(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.confirm')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);

        if ($governance['mode'] === 'AUTHORITY_CONTROLLED') {
            return $governance['authority_office_unit_id'] > 0
                && $user->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']);
        }

        $leadUnitId = (int) ($meeting->units()->where('role', 'REQUESTING')->value('organizational_unit_id') ?: ($governance['required_unit_ids'][0] ?? 0));
        return $leadUnitId > 0 && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $leadUnitId);
    }

    private function hasFormalRole(User $user, array $unitIds, array $roles): bool
    {
        foreach (array_values(array_unique(array_map('intval', $unitIds))) as $unitId) {
            foreach ($roles as $role) {
                if ($user->hasScopedRole($role, $unitId)) return true;
            }
        }
        return false;
    }
}
