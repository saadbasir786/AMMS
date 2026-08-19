<?php

namespace App\Policies;

use App\Models\OrganizationalUnit;
use App\Models\User;

class OrganizationalUnitPolicy
{
    public function before(User $user): ?bool
    {
        if (!$user->isActive()) return false;
        return $user->hasScopedRole('SYSTEM_ADMIN') && $user->can('organization.manage') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isActive() && ($user->can('organization.manage') || $user->can('meeting-request.view') || $user->can('authority-initiated-meeting.prepare'));
    }

    public function view(User $user, OrganizationalUnit $unit): bool
    {
        return $user->isActive() && ($user->can('organization.manage') || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unit->id) || $user->hasScopedRole('DIRECTOR', $unit->id) || $user->hasScopedRole('MEETING_COORDINATOR', $unit->id));
    }

    public function create(User $user): bool { return $user->isActive() && $user->can('organization.manage'); }
    public function update(User $user, OrganizationalUnit $unit): bool { return $user->isActive() && $user->can('organization.manage'); }
    public function delete(User $user, OrganizationalUnit $unit): bool { return false; }
}
