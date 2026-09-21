<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;
use App\Support\ScheduleGovernanceResolver;

class MeetingGovernancePolicy extends MeetingPolicy
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
            $formalUnitIds = $meeting->units->whereIn('role', ['REQUESTING', 'REQUESTER', 'PARTICIPATING'])
                ->pluck('organizational_unit_id')->map(fn ($id): int => (int) $id)->all();
            if ($this->hasFormalRole($user, $formalUnitIds, ['DIRECTOR', 'DEPUTY_DIRECTOR', 'EXECUTIVE_MANAGEMENT'])) return true;
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

    public function meetingScheduleConflictPreview(User $user, Meeting $meeting): bool
    {
        return $this->meetingReschedulePeerPropose($user, $meeting)
            || $this->meetingRescheduleAuthorityPropose($user, $meeting)
            || $this->meetingRescheduleConfirm($user, $meeting);
    }

    public function meetingRescheduleCreateCase(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.create')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);

        if ($governance['mode'] === 'AUTHORITY_CONTROLLED' && $governance['authority_office_unit_id'] > 0
            && $user->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id'])) {
            return true;
        }

        return $this->hasFormalRole($user, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT', 'DIRECTOR']);
    }

    public function meetingReschedulePeerPropose(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.propose')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);
        if (!in_array($governance['mode'], ['INTERNAL_SINGLE_UNIT', 'PEER_MULTI_UNIT'], true)) return false;

        if ($governance['mode'] === 'INTERNAL_SINGLE_UNIT') {
            $unitId = (int) ($governance['required_unit_ids'][0] ?? 0);
            $proposal = $this->internalProposal($meeting);

            return $unitId > 0
                && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId)
                && (!$proposal || ((int) $proposal->proposed_by_user_id === (int) $user->id
                    && in_array($proposal->status, ['DRAFT', 'RETURNED_FOR_CORRECTION'], true)));
        }

        return $this->hasFormalRole($user, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT']);
    }

    public function meetingRescheduleAuthorityPropose(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.propose')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);
        return $governance['mode'] === 'AUTHORITY_CONTROLLED'
            && $governance['authority_office_unit_id'] > 0
            && $user->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']);
    }

    public function meetingRescheduleAcknowledge(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.acknowledge')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);

        if ($governance['mode'] === 'INTERNAL_SINGLE_UNIT') {
            $proposal = $this->internalProposal($meeting);
            $unitId = (int) ($governance['required_unit_ids'][0] ?? 0);

            return $unitId > 0
                && in_array($proposal?->status, ['SUBMITTED', 'ACTIVE'], true)
                && (int) $proposal->proposed_by_user_id !== (int) $user->id
                && $user->hasScopedRole('DIRECTOR', $unitId);
        }

        return $this->hasFormalRole($user, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT', 'DIRECTOR']);
    }

    public function meetingRescheduleSubmit(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.propose')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);
        $proposal = $this->internalProposal($meeting);
        $unitId = (int) ($governance['required_unit_ids'][0] ?? 0);

        return $governance['mode'] === 'INTERNAL_SINGLE_UNIT'
            && $proposal
            && in_array($proposal->status, ['DRAFT', 'RETURNED_FOR_CORRECTION'], true)
            && (int) $proposal->proposed_by_user_id === (int) $user->id
            && $unitId > 0
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    public function meetingRescheduleReturn(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.acknowledge')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);
        $proposal = $this->internalProposal($meeting);
        $unitId = (int) ($governance['required_unit_ids'][0] ?? 0);

        return $governance['mode'] === 'INTERNAL_SINGLE_UNIT'
            && in_array($proposal?->status, ['SUBMITTED', 'ACTIVE'], true)
            && $unitId > 0
            && $user->hasScopedRole('DIRECTOR', $unitId)
            && (int) $proposal->proposed_by_user_id !== (int) $user->id;
    }

    public function meetingRescheduleRequestAlternative(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.request-alternative')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);
        return $this->hasFormalRole($user, $governance['required_unit_ids'], ['EXECUTIVE_MANAGEMENT', 'DIRECTOR']);
    }

    public function meetingRescheduleResolveAlternative(User $user, Meeting $meeting): bool
    {
        return $this->meetingRescheduleAuthorityPropose($user, $meeting) && $user->can('meeting-reschedule.request-alternative');
    }

    public function meetingRescheduleConfirm(User $user, Meeting $meeting): bool
    {
        if (!$this->view($user, $meeting) || !$user->can('meeting-reschedule.confirm')) return false;
        $governance = app(ScheduleGovernanceResolver::class)->resolve($meeting);

        if ($governance['mode'] === 'AUTHORITY_CONTROLLED') {
            return $governance['authority_office_unit_id'] > 0
                && $user->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']);
        }

        $leadUnitId = (int) ($meeting->units()->where('role', 'REQUESTING')->value('organizational_unit_id') ?: ($governance['required_unit_ids'][0] ?? 0));
        if ($governance['mode'] === 'INTERNAL_SINGLE_UNIT') {
            $proposal = $this->internalProposal($meeting);

            return $leadUnitId > 0
                && $proposal?->status === 'APPROVED'
                && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $leadUnitId);
        }

        return $leadUnitId > 0 && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $leadUnitId);
    }

    private function internalProposal(Meeting $meeting): ?\App\Models\MeetingRescheduleProposal
    {
        $case = $meeting->activeRescheduleCase()->first();
        if (!$case) return null;

        return $case->proposals()
            ->whereIn('status', ['ACTIVE', 'DRAFT', 'SUBMITTED', 'RETURNED_FOR_CORRECTION', 'APPROVED'])
            ->latest('id')
            ->first();
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
