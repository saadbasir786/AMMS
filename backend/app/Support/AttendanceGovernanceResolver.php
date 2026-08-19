<?php

namespace App\Support;

use App\Models\Meeting;
use App\Models\User;

class AttendanceGovernanceResolver
{
    public function canOverride(User $actor, Meeting $meeting): bool
    {
        if (!$actor->isActive() || !$actor->can('attendance.override')) return false;
        if ((int) $meeting->coordinator_id === (int) $actor->id) return true;
        if ($actor->hasScopedRole('MEETING_COORDINATOR', (int) $meeting->organizing_unit_id)) return true;
        $request = $meeting->request()->with('authorityContext')->first();
        $officeId = $request?->authorityContext?->authority_office_unit_id;
        if ($officeId && ($actor->hasScopedRole('EXECUTIVE_SECRETARY', (int) $officeId) || $actor->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId))) return true;
        return false;
    }
}
