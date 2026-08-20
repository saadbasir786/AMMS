<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingDecisionFollowUp;
use App\Models\MeetingMinutes;
use App\Models\MeetingMinuteVersion;
use App\Models\MeetingMinuteVersionDecision;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Collection;
class DecisionFollowUpGovernanceResolver
{
    public function source(Meeting $meeting): array
    {
        $minutes = $meeting->relationLoaded('minutes') ? $meeting->minutes : $meeting->minutes()->first();
        abort_unless($minutes && $minutes->status === MeetingMinutes::FINALIZED, 422, 'FOLLOWUP_MINUTES_NOT_FINALIZED');
        abort_unless($meeting->status === 'COMPLETED', 422, 'FOLLOWUP_MEETING_NOT_COMPLETED');
        $versionId = (int) $minutes->current_version_id;
        abort_unless($versionId > 0, 422, 'FOLLOWUP_FINAL_VERSION_MISSING');
        $version = MeetingMinuteVersion::query()->whereKey($versionId)->where('meeting_minutes_id', $minutes->id)->first();
        abort_unless($version, 422, 'FOLLOWUP_FINAL_VERSION_MISSING');
        return [$minutes, $version];
    }

    public function ownerRole(Meeting $meeting): string
    {
        return $this->isAuthorityFlow($meeting) ? 'EXECUTIVE_SECRETARY' : 'MEETING_COORDINATOR';
    }

    public function ownerUnitId(Meeting $meeting): ?int
    {
        if ($this->isAuthorityFlow($meeting)) {
            return (int) ($meeting->authorityContext()->value('authority_office_unit_id') ?: 0) ?: null;
        }
        return (int) ($meeting->organizing_unit_id ?: 0) ?: null;
    }

    public function isAuthorityFlow(Meeting $meeting): bool
    {
        $flow = (string) ($meeting->request?->flow_type ?? '');
        return $meeting->authorityContext()->exists()
            || in_array($flow, ['DIRECTORATE_WITH_HIGH_AUTHORITY', 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', 'HIGH_AUTHORITY_INITIATED'], true);
    }

    public function isFollowUpOwner(Meeting $meeting, User $actor): bool
    {
        if (!$actor->isActive() || !$actor->can('decision.followup.view')) return false;
        $role = $this->ownerRole($meeting);
        $unitId = $this->ownerUnitId($meeting);
        return $actor->hasScopedRole($role, $unitId);
    }

    public function mayInitialize(Meeting $meeting, User $actor): bool
    {
        return $this->isFollowUpOwner($meeting, $actor) && $actor->can('decision.followup.initialize');
    }

    public function mayClassify(MeetingDecisionFollowUp $followup, User $actor): bool
    {
        return $followup->meeting && $this->isFollowUpOwner($followup->meeting, $actor) && $actor->can('decision.followup.classify');
    }

    public function mayCreateActionItem(MeetingDecisionFollowUp $followup, User $actor): bool
    {
        return $this->mayClassify($followup, $actor) && $actor->can('action-item.create') && $followup->status !== MeetingDecisionFollowUp::NO_ACTION_REQUIRED;
    }

    public function mayViewActionItem(MeetingActionItem $item, User $actor): bool
    {
        if (!$actor->isActive() || !$actor->can('action-item.view')) return false;
        $meeting = $item->followup?->meeting;
        if (!$meeting) return false;
        if ($this->isFollowUpOwner($meeting, $actor)) return true;
        if ($item->assigned_employee_id && (int) $item->assigned_employee_id === (int) $actor->employee_id) return true;
        return $this->mayManageUnit($item, $actor);
    }

    public function mayWorkItem(MeetingActionItem $item, User $actor, string $permission): bool
    {
        if (!$this->mayViewActionItem($item, $actor) || !$actor->can($permission)) return false;
        if ($this->isFollowUpOwner($item->followup->meeting, $actor)) return true;
        if ($item->assigned_employee_id && (int) $item->assigned_employee_id === (int) $actor->employee_id) return true;
        return $this->mayManageUnit($item, $actor);
    }

    public function mayManageUnit(MeetingActionItem $item, User $actor): bool
    {
        $unitId = (int) $item->responsible_unit_id;
        return $actor->hasScopedRole('DIRECTOR', $unitId)
            || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId)
            || $actor->hasScopedRole('MEETING_COORDINATOR', $unitId);
    }

    public function mayChangeEmployee(MeetingActionItem $item, User $actor): bool
    {
        return $this->mayViewActionItem($item, $actor) && $actor->can('action-item.assign')
            && ($this->isFollowUpOwner($item->followup->meeting, $actor) || $this->mayManageUnit($item, $actor));
    }

    public function mayChangeUnit(MeetingActionItem $item, User $actor): bool
    {
        return $this->isFollowUpOwner($item->followup->meeting, $actor) && $actor->can('action-item.change-unit');
    }

    public function mayVerify(MeetingActionItem $item, User $actor): bool
    {
        return $this->isFollowUpOwner($item->followup->meeting, $actor) && $actor->can('action-item.verify-completion');
    }

    public function mayCancel(MeetingActionItem $item, User $actor): bool
    {
        return $this->isFollowUpOwner($item->followup->meeting, $actor) && $actor->can('action-item.cancel');
    }

    public function mayReviewExtension(MeetingActionItem $item, User $actor): bool
    {
        return $this->isFollowUpOwner($item->followup->meeting, $actor) && $actor->can('action-item.extension.review');
    }

    public function validateUnit(int $unitId, Meeting $meeting): OrganizationalUnit
    {
        $unit = OrganizationalUnit::query()->findOrFail($unitId);
        abort_unless(($unit->status ?: 'ACTIVE') === 'ACTIVE', 422, 'ACTION_ITEM_RESPONSIBLE_UNIT_INACTIVE');
        $meetingOrganization = $meeting->organizingUnit?->organization_id;
        if ($meetingOrganization !== null && $unit->organization_id !== null) {
            abort_unless((int) $meetingOrganization === (int) $unit->organization_id, 422, 'ACTION_ITEM_CROSS_ORGANIZATION_UNIT');
        }
        return $unit;
    }

    public function validateEmployee(?int $employeeId, int $unitId, Meeting $meeting): void
    {
        if (!$employeeId) return;
        $employee = \App\Models\Employee::query()->with('unit')->findOrFail($employeeId);
        abort_unless(($employee->status ?: 'ACTIVE') === 'ACTIVE', 422, 'ACTION_ITEM_ASSIGNEE_INACTIVE');
        abort_unless((int) $employee->organizational_unit_id === $unitId, 422, 'ACTION_ITEM_ASSIGNEE_OUTSIDE_UNIT');
        $meetingOrganization = $meeting->organizingUnit?->organization_id;
        $employeeOrganization = $employee->unit?->organization_id;
        if ($meetingOrganization !== null && $employeeOrganization !== null) {
            abort_unless((int) $meetingOrganization === (int) $employeeOrganization, 422, 'ACTION_ITEM_CROSS_ORGANIZATION_EMPLOYEE');
        }
    }

    public function nextSequence(MeetingDecisionFollowUp $followup): int
    {
        return ((int) $followup->actionItems()->lockForUpdate()->max('sequence_number')) + 1;
    }

    public function refreshFollowupStatus(MeetingDecisionFollowUp $followup): void
    {
        if ($followup->status === MeetingDecisionFollowUp::NO_ACTION_REQUIRED) return;
        $items = $followup->actionItems()->get();
        if ($items->isEmpty()) {
            if ($followup->status === MeetingDecisionFollowUp::CLOSED) $followup->update(['status' => MeetingDecisionFollowUp::ACTIVE]);
            return;
        }
        $closed = $items->every(fn (MeetingActionItem $item): bool => in_array($item->status, [MeetingActionItem::COMPLETED, MeetingActionItem::CANCELLED], true));
        $followup->update(['status' => $closed ? MeetingDecisionFollowUp::CLOSED : MeetingDecisionFollowUp::ACTIVE]);
    }

    public function sourceDecisionIds(MeetingMinuteVersion $version): Collection
    {
        return MeetingMinuteVersionDecision::query()->where('meeting_minute_version_id', $version->id)->pluck('id');
    }
}
