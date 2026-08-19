<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Collection;

class ParticipantGovernanceResolver
{
    public function __construct(private readonly ScheduleGovernanceResolver $scheduleGovernance)
    {
    }

    public function resolve(Meeting $meeting): array
    {
        $schedule = $this->scheduleGovernance->resolve($meeting);
        $authorityOfficeUnitId = (int) ($meeting->authorityContext?->authority_office_unit_id ?? 0);
        $formalUnits = $schedule['formal_units']->values();

        return [
            'flow_type' => $schedule['flow_type'],
            'mode' => $schedule['mode'],
            'formal_units' => $formalUnits,
            'formal_unit_ids' => $formalUnits->pluck('organizational_unit_id')->map(fn ($id): int => (int) $id)->all(),
            'authority_controlled' => (bool) $schedule['authority_controlled'],
            'authority_office_unit_id' => $authorityOfficeUnitId,
            'authority_employee_id' => (int) ($meeting->authorityContext?->authority_employee_id ?? 0),
            'domains' => [
                'UNIT' => $formalUnits->map(fn ($unit): array => [
                    'owner_type' => 'UNIT',
                    'owner_unit_id' => (int) $unit->organizational_unit_id,
                    'unit_role' => $unit->role,
                ])->values()->all(),
                'AUTHORITY' => $authorityOfficeUnitId > 0 && $schedule['authority_controlled'] ? [[
                    'owner_type' => 'AUTHORITY',
                    'owner_unit_id' => null,
                    'authority_office_unit_id' => $authorityOfficeUnitId,
                ]] : [],
            ],
        ];
    }

    public function unitIds(Meeting $meeting): array
    {
        return $this->resolve($meeting)['formal_unit_ids'];
    }

    public function isAuthorityOwner(Meeting $meeting, User $actor): bool
    {
        $officeId = (int) ($meeting->authorityContext?->authority_office_unit_id ?? 0);
        return $officeId > 0 && $this->resolve($meeting)['authority_controlled']
            && ($actor->hasScopedRole('AUTHORITY_OFFICE', $officeId) || $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeId));
    }

    public function actorOwner(Meeting $meeting, User $actor): ?array
    {
        if ($this->isAuthorityOwner($meeting, $actor)) {
            return ['owner_type' => 'AUTHORITY', 'owner_unit_id' => null];
        }

        $employeeUnitId = (int) ($actor->employee?->organizational_unit_id ?? 0);
        if (!$employeeUnitId) return null;

        foreach ($this->resolve($meeting)['formal_units'] as $formalUnit) {
            $unit = $formalUnit->unit ?? null;
            if ($unit && $unit->isWithinScope($employeeUnitId)) {
                return ['owner_type' => 'UNIT', 'owner_unit_id' => (int) $formalUnit->organizational_unit_id];
            }
        }

        return null;
    }

    public function ownsDomain(Meeting $meeting, User $actor, string $ownerType, ?int $ownerUnitId): bool
    {
        if ($ownerType === 'AUTHORITY') return $ownerUnitId === null && $this->isAuthorityOwner($meeting, $actor);
        if ($ownerType !== 'UNIT' || !$ownerUnitId) return false;
        $owner = $this->actorOwner($meeting, $actor);
        return $owner && $owner['owner_type'] === 'UNIT' && (int) $owner['owner_unit_id'] === $ownerUnitId;
    }

    public function mayPrepare(Meeting $meeting, User $actor, string $ownerType, ?int $ownerUnitId): bool
    {
        if (!$actor->isActive() || !$actor->can('meeting-participant-change.create')) return false;
        if ($ownerType === 'AUTHORITY') {
            return $actor->can('meeting-participant-change.manage') && $this->isAuthorityOwner($meeting, $actor);
        }
        return $actor->can('meeting-participant-change.manage') && $this->ownsDomain($meeting, $actor, $ownerType, $ownerUnitId)
            && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $ownerUnitId) || $actor->hasScopedRole('MEETING_COORDINATOR', $ownerUnitId));
    }

    public function mayConfirm(Meeting $meeting, User $actor, string $ownerType, ?int $ownerUnitId): bool
    {
        if (!$actor->isActive() || !$actor->can('meeting-participant-change.confirm')) return false;
        if ($ownerType === 'AUTHORITY') return $this->isAuthorityOwner($meeting, $actor);
        if ($ownerType !== 'UNIT' || !$ownerUnitId || $this->resolve($meeting)['authority_controlled']) return false;
        if ($actor->hasScopedRole('SYSTEM_ADMIN')) return true;
        foreach ($this->resolve($meeting)['formal_unit_ids'] as $formalUnitId) {
            if ($actor->hasScopedRole('DIRECTOR', (int) $formalUnitId)) return true;
        }
        return false;
    }

    public function mayView(Meeting $meeting, User $actor): bool
    {
        return $actor->isActive() && $actor->can('meeting-participant-change.view-history')
            && (app(\App\Policies\MeetingPolicy::class)->view($actor, $meeting));
    }
}
