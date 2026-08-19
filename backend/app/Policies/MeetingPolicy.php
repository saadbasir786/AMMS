<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function before(User $user): ?bool
    {
        if (!$user->isActive()) return false;
        return $user->hasScopedRole('SYSTEM_ADMIN') && $user->can('meeting.view') ? true : null;
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
        if ($meeting->coordinator_id === $user->id || $user->hasScopedRole('DIRECTOR', $meeting->organizing_unit_id) || $user->hasScopedRole('MEETING_COORDINATOR', $meeting->organizing_unit_id)) return true;
        if ($user->employee && $meeting->participants()->where('employee_id', $user->employee_id)->exists()) return true;
        if ($isHighAuthorityFlow) {
            $context = $meeting->authorityContext()->first();
            return $context && ((int) $context->authority_employee_id === (int) $user->employee_id || $user->hasScopedRole('AUTHORITY_OFFICE', (int) $context->authority_office_unit_id) || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $context->authority_office_unit_id));
        }
        return false;
    }

    public function cancel(User $user, Meeting $meeting): bool
    {
        return false;
    }
}
