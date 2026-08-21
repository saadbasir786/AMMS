<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;
use App\Support\ScheduleGovernanceResolver;

class FlowAwareMeetingPolicy extends MeetingPolicy
{
    public function view(User $user, Meeting $meeting): bool
    {
        if (!$user->isActive()) return false;

        $meeting->loadMissing(['request', 'units', 'participants', 'authorityContext']);
        $isAuthorityFlow = in_array((string) $meeting->request?->flow_type, [
            'DIRECTORATE_WITH_HIGH_AUTHORITY',
            'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY',
            'HIGH_AUTHORITY_INITIATED',
        ], true) || $meeting->authorityContext !== null;

        if ($user->can('meeting.view')) {
            $formalUnitIds = $meeting->units->whereIn('role', ['REQUESTING', 'PARTICIPATING'])
                ->pluck('organizational_unit_id')->map(fn ($id): int => (int) $id)->all();
            if ($this->hasFormalRole($user, $formalUnitIds, ['DIRECTOR', 'EXECUTIVE_MANAGEMENT'])) return true;
            if ($user->employee_id && $meeting->participants->contains(fn ($participant): bool => (int) $participant->employee_id === (int) $user->employee_id)) return true;
        }

        if ($isAuthorityFlow && $user->can('authority-request.view')) {
            $context = $meeting->authorityContext;
            if (!$context) return false;
            if ($user->employee_id && (int) $context->authority_employee_id === (int) $user->employee_id) return true;
            if ($context->authority_office_unit_id && ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $context->authority_office_unit_id)
                || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $context->authority_office_unit_id))) return true;
        }

        return false;
    }

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
