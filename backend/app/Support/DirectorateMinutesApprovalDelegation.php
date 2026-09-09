<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AmmsCapabilityScope;
use App\Models\Meeting;
use App\Models\User;

class DirectorateMinutesApprovalDelegation
{
    public function scopeFor(Meeting $meeting, User $actor, int $unitId): ?AmmsCapabilityScope
    {
        if (
            !$actor->isActive()
            || !$actor->can('meeting.minutes.review-delegated')
            || (
                !$actor->hasScopedRole('DEPUTY_DIRECTOR', $unitId)
                && !$actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId)
            )
            || !$meeting->units()
                ->whereIn('role', ['REQUESTING', 'REQUESTER', 'PARTICIPATING'])
                ->where('organizational_unit_id', $unitId)
                ->exists()
        ) {
            return null;
        }

        return AmmsCapabilityScope::query()
            ->active()
            ->where('user_id', $actor->id)
            ->where('capability', SpecialCapability::DIRECTORATE_MINUTES_APPROVAL)
            ->where('organizational_unit_id', $unitId)
            ->first();
    }
}
