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
        if ($meeting->coordinator_id === $user->id || $user->hasScopedRole('DIRECTOR', $meeting->organizing_unit_id) || $user->hasScopedRole('DEPUTY_DIRECTOR', $meeting->organizing_unit_id) || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $meeting->organizing_unit_id)) return true;
        $meetingUnitIds = $meeting->units()->whereIn('role', ['REQUESTING', 'REQUESTER', 'PARTICIPATING'])->pluck('organizational_unit_id');
        if ($meetingUnitIds->contains(fn ($unitId): bool => $user->hasScopedRole('DIRECTOR', (int) $unitId) || $user->hasScopedRole('DEPUTY_DIRECTOR', (int) $unitId))) return true;
        if ($user->employee && $meeting->participants()->where('employee_id', $user->employee_id)->exists()) return true;
        if ($isHighAuthorityFlow) {
            $context = $meeting->authorityContext()->first();
            return $context && ((int) $context->authority_employee_id === (int) $user->employee_id || $user->hasScopedRole('AUTHORITY_OFFICE', (int) $context->authority_office_unit_id) || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $context->authority_office_unit_id));
        }
        return false;
    }

    public function meetingRescheduleViewHistory(User $user, Meeting $meeting): bool
    {
        return $this->canView($user, $meeting) && $user->can('meeting-reschedule.view-history');
    }

    public function meetingRescheduleCreateCase(User $user, Meeting $meeting): bool
    {
        return $this->canView($user, $meeting) && $user->can('meeting-reschedule.create');
    }

    public function meetingReschedulePeerPropose(User $user, Meeting $meeting): bool
    {
        if (!$this->canView($user, $meeting) || !$user->can('meeting-reschedule.propose')) return false;
        $governance = app(\App\Support\ScheduleGovernanceResolver::class)->resolve($meeting);
        return $governance['mode'] === 'PEER_MULTI_UNIT' && in_array((int) ($user->employee?->organizational_unit_id ?? 0), $governance['required_unit_ids'], true);
    }

    public function meetingRescheduleAuthorityPropose(User $user, Meeting $meeting): bool
    {
        if (!$this->canView($user, $meeting) || !$user->can('meeting-reschedule.propose')) return false;
        $governance = app(\App\Support\ScheduleGovernanceResolver::class)->resolve($meeting);
        return $governance['mode'] === 'AUTHORITY_CONTROLLED'
            && $governance['authority_office_unit_id'] > 0
            && $user->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']);
    }

    public function meetingRescheduleAcknowledge(User $user, Meeting $meeting): bool
    {
        if (!$this->canView($user, $meeting) || !$user->can('meeting-reschedule.acknowledge')) return false;
        $governance = app(\App\Support\ScheduleGovernanceResolver::class)->resolve($meeting);
        return in_array((int) ($user->employee?->organizational_unit_id ?? 0), $governance['required_unit_ids'], true);
    }

    public function meetingRescheduleRequestAlternative(User $user, Meeting $meeting): bool
    {
        if (!$this->canView($user, $meeting) || !$user->can('meeting-reschedule.request-alternative')) return false;
        $governance = app(\App\Support\ScheduleGovernanceResolver::class)->resolve($meeting);
        return in_array((int) ($user->employee?->organizational_unit_id ?? 0), $governance['required_unit_ids'], true);
    }

    public function meetingRescheduleResolveAlternative(User $user, Meeting $meeting): bool
    {
        return $this->meetingRescheduleAuthorityPropose($user, $meeting) && $user->can('meeting-reschedule.request-alternative');
    }

    public function meetingRescheduleConfirm(User $user, Meeting $meeting): bool
    {
        if (!$this->canView($user, $meeting) || !$user->can('meeting-reschedule.confirm')) return false;
        $governance = app(\App\Support\ScheduleGovernanceResolver::class)->resolve($meeting);
        if ($governance['mode'] === 'AUTHORITY_CONTROLLED') {
            return $governance['authority_office_unit_id'] > 0 && $user->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']);
        }
        return $user->id === $meeting->coordinator_id || in_array((int) ($user->employee?->organizational_unit_id ?? 0), $governance['required_unit_ids'], true);
    }

    public function participantChangeViewHistory(User $user, Meeting $meeting): bool
    {
        return app(\App\Support\ParticipantGovernanceResolver::class)->mayView($meeting, $user);
    }

    public function participantChangeCreateCase(User $user, Meeting $meeting): bool
    {
        if (!$user->isActive() || !$user->can('meeting-participant-change.create') || !$this->view($user, $meeting)) return false;
        $owner = app(\App\Support\ParticipantGovernanceResolver::class)->actorOwner($meeting, $user);
        return $owner && app(\App\Support\ParticipantGovernanceResolver::class)->mayPrepare($meeting, $user, $owner['owner_type'], $owner['owner_unit_id']);
    }

    public function participantChangeManageCase(User $user, Meeting $meeting, ?string $ownerType = null, ?int $ownerUnitId = null): bool
    {
        if (!$user->isActive() || !$this->view($user, $meeting)) return false;
        $owner = $ownerType ? ['owner_type' => $ownerType, 'owner_unit_id' => $ownerUnitId] : app(\App\Support\ParticipantGovernanceResolver::class)->actorOwner($meeting, $user);
        return $owner && app(\App\Support\ParticipantGovernanceResolver::class)->mayPrepare($meeting, $user, $owner['owner_type'], $owner['owner_unit_id']);
    }

    public function participantChangeConfirm(User $user, Meeting $meeting, string $ownerType, ?int $ownerUnitId = null): bool
    {
        return $this->view($user, $meeting)
            && app(\App\Support\ParticipantGovernanceResolver::class)->mayConfirm($meeting, $user, $ownerType, $ownerUnitId);
    }

    public function participantChangeReturn(User $user, Meeting $meeting, string $ownerType, ?int $ownerUnitId = null): bool
    {
        return $this->participantChangeConfirm($user, $meeting, $ownerType, $ownerUnitId) && $user->can('meeting-participant-change.return');
    }

    public function participantChangeReject(User $user, Meeting $meeting, string $ownerType, ?int $ownerUnitId = null): bool
    {
        return $this->participantChangeConfirm($user, $meeting, $ownerType, $ownerUnitId) && $user->can('meeting-participant-change.reject');
    }

    private function canView(User $user, Meeting $meeting): bool
    {
        return $this->view($user, $meeting);
    }

    public function terminalAssignmentView(User $user, Meeting $meeting): bool
    {
        $resolver = app(\App\Support\TerminalAssignmentGovernanceResolver::class);
        if ($user->isActive() && $user->can('meeting-terminal.view') && $user->hasScopedRole('SYSTEM_ADMIN')) return true;
        return $this->view($user, $meeting) && $resolver->mayView($meeting, $user);
    }

    public function terminalAssignmentAssign(User $user, Meeting $meeting): bool
    {
        return $this->terminalAssignmentView($user, $meeting) && app(\App\Support\TerminalAssignmentGovernanceResolver::class)->mayAssign($meeting, $user);
    }

    public function terminalAssignmentRefresh(User $user, Meeting $meeting, MeetingTerminalAssignment $assignment): bool
    {
        return (int) $assignment->meeting_id === (int) $meeting->id
            && $this->terminalAssignmentView($user, $meeting)
            && app(\App\Support\TerminalAssignmentGovernanceResolver::class)->mayRefresh($meeting, $user);
    }

    public function terminalAssignmentRevoke(User $user, Meeting $meeting, MeetingTerminalAssignment $assignment): bool
    {
        return (int) $assignment->meeting_id === (int) $meeting->id
            && $this->terminalAssignmentView($user, $meeting)
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
