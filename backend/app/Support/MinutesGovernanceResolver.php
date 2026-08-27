<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Meeting;
use App\Models\User;

class MinutesGovernanceResolver
{
    public function isAuthorityControlled(Meeting $meeting): bool
    {
        $flowType = (string) ($meeting->request?->flow_type ?? '');
        return in_array($flowType, ['DIRECTORATE_WITH_HIGH_AUTHORITY', 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', 'HIGH_AUTHORITY_INITIATED'], true)
            || $meeting->authorityContext()->exists();
    }

    public function authorityOfficeUnitId(Meeting $meeting): int
    {
        return (int) ($meeting->request?->authorityContext?->authority_office_unit_id ?? $meeting->authorityContext?->authority_office_unit_id ?? 0);
    }

    public function mayPrepare(Meeting $meeting, User $actor): bool
    {
        if (!$actor->isActive() || !$actor->can('meeting.minutes.prepare')) return false;
        if ($this->isAuthorityControlled($meeting)) {
            $officeId = $this->authorityOfficeUnitId($meeting);
            return $officeId > 0 && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeId);
        }
        return (int) $meeting->coordinator_id === (int) $actor->id
            || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $meeting->organizing_unit_id);
    }

    public function maySubmit(Meeting $meeting, User $actor): bool
    {
        return $this->mayPrepare($meeting, $actor) && $actor->can('meeting.minutes.submit');
    }

    public function mayFinalize(Meeting $meeting, User $actor): bool
    {
        if (!$actor->isActive() || !$actor->can('meeting.minutes.finalize')) return false;
        if ($this->isAuthorityControlled($meeting)) {
            $officeId = $this->authorityOfficeUnitId($meeting);
            return $officeId > 0 && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeId);
        }
        return (int) $meeting->coordinator_id === (int) $actor->id || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $meeting->organizing_unit_id);
    }

    public function mayReviewUnit(Meeting $meeting, User $actor, int $unitId): bool
    {
        return $actor->isActive() && $actor->can('meeting.minutes.review') && $actor->hasScopedRole('DIRECTOR', $unitId)
            && $meeting->units()->whereIn('role', ['REQUESTING', 'REQUESTER', 'PARTICIPATING'])->where('organizational_unit_id', $unitId)->exists();
    }

    public function mayApproveHighAuthority(Meeting $meeting, User $actor): bool
    {
        if (!$actor->isActive() || !$actor->can('meeting.minutes.approve_authority')) return false;
        $context = $meeting->authorityContext()->first();
        return $context && (int) $context->authority_employee_id === (int) $actor->employee_id;
    }
}
