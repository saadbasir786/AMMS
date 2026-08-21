<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;
use App\Models\MeetingTerminalAssignment;

class MeetingPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (!$user->isActive()) return false;
        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->can('meeting.view');
    }

    public function view(User $user, Meeting $meeting): bool
    {
        if (!$user->isActive()) return false;
        $isHighAuthorityFlow = $meeting->request?->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' || $meeting->authorityContext()->exists();
        if (!$user->can('meeting.view') && !($isHighAuthorityFlow && $user->can('authority-request.view'))) return false;
        if ($meeting->coordinator_id === $user->id || $user->hasScopedRole('DIRECTOR', $meeting->organizing_unit_id) || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $meeting->organizing_unit_id)) return true;
        if ($user->employee && $meeting->participants()->where('employee_id', $user->employee_id)->exists()) return true;
        if ($isHighAuthorityFlow) {
            $context = $meeting->authorityContext()->first();
            return $context && ((int) $context->authority_employee_id === (int) $user->employee_id || $user->hasScopedRole('AUTHORITY_OFFICE', (int) $context->authority_office_unit_id) || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $context->authority_office_unit_id));
        }
        return false;
    }

    public function flow07ViewHistory(User $user, Meeting $meeting): bool
    {
        return $this->canView($user, $meeting) && $user->can('meeting-reschedule.view-history');
    }

    public function flow07CreateCase(User $user, Meeting $meeting): bool
    {
        return $this->canView($user, $meeting) && $user->can('meeting-reschedule.create');
    }

    public function flow07PeerPropose(User $user, Meeting $meeting): bool
    {
        if (!$this->canView($user, $meeting) || !$user->can('meeting-reschedule.propose')) return false;
        $governance = app(\App\Support\ScheduleGovernanceResolver::class)->resolve($meeting);
        return $governance['mode'] === 'PEER_MULTI_UNIT' && in_array((int) ($user->employee?->organizational_unit_id ?? 0), $governance['required_unit_ids'], true);
    }

    public function flow07AuthorityPropose(User $user, Meeting $meeting): bool
    {
        if (!$this->canView($user, $meeting) || !$user->can('meeting-reschedule.propose')) return false;
        $governance = app(\App\Support\ScheduleGovernanceResolver::class)->resolve($meeting);
        return $governance['mode'] === 'AUTHORITY_CONTROLLED'
            && $governance['authority_office_unit_id'] > 0
            && $user->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']);
    }

    public function flow07Acknowledge(User $user, Meeting $meeting): bool
    {
        if (!$this->canView($user, $meeting) || !$user->can('meeting-reschedule.acknowledge')) return false;
        $governance = app(\App\Support\ScheduleGovernanceResolver::class)->resolve($meeting);
        return in_array((int) ($user->employee?->organizational_unit_id ?? 0), $governance['required_unit_ids'], true);
    }

    public function flow07RequestAlternative(User $user, Meeting $meeting): bool
    {
        if (!$this->canView($user, $meeting) || !$user->can('meeting-reschedule.request-alternative')) return false;
        $governance = app(\App\Support\ScheduleGovernanceResolver::class)->resolve($meeting);
        return in_array((int) ($user->employee?->organizational_unit_id ?? 0), $governance['required_unit_ids'], true);
    }

    public function flow07ResolveAlternative(User $user, Meeting $meeting): bool
    {
        return $this->flow07AuthorityPropose($user, $meeting) && $user->can('meeting-reschedule.request-alternative');
    }

    public function flow07Confirm(User $user, Meeting $meeting): bool
    {
        if (!$this->canView($user, $meeting) || !$user->can('meeting-reschedule.confirm')) return false;
        $governance = app(\App\Support\ScheduleGovernanceResolver::class)->resolve($meeting);
        if ($governance['mode'] === 'AUTHORITY_CONTROLLED') {
            return $governance['authority_office_unit_id'] > 0 && $user->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']);
        }
        return $user->id === $meeting->coordinator_id || in_array((int) ($user->employee?->organizational_unit_id ?? 0), $governance['required_unit_ids'], true);
    }

    public function flow08ViewHistory(User $user, Meeting $meeting): bool
    {
        return app(\App\Support\ParticipantGovernanceResolver::class)->mayView($meeting, $user);
    }

    public function flow08CreateCase(User $user, Meeting $meeting): bool
    {
        if (!$user->isActive() || !$user->can('meeting-participant-change.create') || !$this->view($user, $meeting)) return false;
        $owner = app(\App\Support\ParticipantGovernanceResolver::class)->actorOwner($meeting, $user);
        return $owner && app(\App\Support\ParticipantGovernanceResolver::class)->mayPrepare($meeting, $user, $owner['owner_type'], $owner['owner_unit_id']);
    }

    public function flow08ManageCase(User $user, Meeting $meeting, ?string $ownerType = null, ?int $ownerUnitId = null): bool
    {
        if (!$user->isActive() || !$this->view($user, $meeting)) return false;
        $owner = $ownerType ? ['owner_type' => $ownerType, 'owner_unit_id' => $ownerUnitId] : app(\App\Support\ParticipantGovernanceResolver::class)->actorOwner($meeting, $user);
        return $owner && app(\App\Support\ParticipantGovernanceResolver::class)->mayPrepare($meeting, $user, $owner['owner_type'], $owner['owner_unit_id']);
    }

    public function flow08Confirm(User $user, Meeting $meeting, string $ownerType, ?int $ownerUnitId = null): bool
    {
        return $this->view($user, $meeting)
            && app(\App\Support\ParticipantGovernanceResolver::class)->mayConfirm($meeting, $user, $ownerType, $ownerUnitId);
    }

    public function flow08Return(User $user, Meeting $meeting, string $ownerType, ?int $ownerUnitId = null): bool
    {
        return $this->flow08Confirm($user, $meeting, $ownerType, $ownerUnitId) && $user->can('meeting-participant-change.return');
    }

    public function flow08Reject(User $user, Meeting $meeting, string $ownerType, ?int $ownerUnitId = null): bool
    {
        return $this->flow08Confirm($user, $meeting, $ownerType, $ownerUnitId) && $user->can('meeting-participant-change.reject');
    }

    private function canView(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting);
    }

    public function flow09View(User $user, Meeting $meeting): bool
    {
        $resolver = app(\App\Support\TerminalAssignmentGovernanceResolver::class);
        if ($user->isActive() && $user->can('meeting-terminal.view') && $user->hasScopedRole('SYSTEM_ADMIN')) return true;
        return $this->view($user, $meeting) && $resolver->mayView($meeting, $user);
    }

    public function flow09Assign(User $user, Meeting $meeting): bool
    {
        return $this->flow09View($user, $meeting) && app(\App\Support\TerminalAssignmentGovernanceResolver::class)->mayAssign($meeting, $user);
    }

    public function flow09Refresh(User $user, Meeting $meeting, MeetingTerminalAssignment $assignment): bool
    {
        return (int) $assignment->meeting_id === (int) $meeting->id
            && $this->flow09View($user, $meeting)
            && app(\App\Support\TerminalAssignmentGovernanceResolver::class)->mayRefresh($meeting, $user);
    }

    public function flow09Revoke(User $user, Meeting $meeting, MeetingTerminalAssignment $assignment): bool
    {
        return (int) $assignment->meeting_id === (int) $meeting->id
            && $this->flow09View($user, $meeting)
            && app(\App\Support\TerminalAssignmentGovernanceResolver::class)->mayRevoke($meeting, $user);
    }

    public function executionView(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting) && $user->can('meeting.minutes.view');
    }

    public function minutesView(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting) && $user->can('meeting.minutes.view');
    }

    public function minutesPrepare(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting) && app(\App\Support\MinutesGovernanceResolver::class)->mayPrepare($meeting, $user);
    }

    public function minutesSubmit(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting) && app(\App\Support\MinutesGovernanceResolver::class)->maySubmit($meeting, $user);
    }

    public function minutesReviewUnit(User $user, Meeting $meeting, int $unitId): bool
    {
        return $this->view($user, $meeting) && app(\App\Support\MinutesGovernanceResolver::class)->mayReviewUnit($meeting, $user, $unitId);
    }

    public function minutesApproveAuthority(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting) && app(\App\Support\MinutesGovernanceResolver::class)->mayApproveHighAuthority($meeting, $user);
    }

    public function minutesFinalize(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting) && app(\App\Support\MinutesGovernanceResolver::class)->mayFinalize($meeting, $user);
    }

    public function minutesDocumentView(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting) && $user->can('meeting.minutes.document.view') && $meeting->minutes()->where('status', \App\Models\MeetingMinutes::FINALIZED)->exists();
    }

    public function executionStart(User $user, Meeting $meeting): bool
    {
        return app(\App\Support\MeetingExecutionGovernanceResolver::class)->mayStart($meeting, $user);
    }

    public function executionEnd(User $user, Meeting $meeting): bool
    {
        return app(\App\Support\MeetingExecutionGovernanceResolver::class)->mayEnd($meeting, $user);
    }

    public function cancel(User $user, Meeting $meeting): bool
    {
        return false;
    }
}
