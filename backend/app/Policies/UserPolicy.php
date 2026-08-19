<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function before(User $user): ?bool
    {
        if (!$user->isActive()) return false;
        return $user->hasScopedRole('SYSTEM_ADMIN') && ($user->can('user.manage') || $user->can('role.manage')) ? true : null;
    }

    private function allowed(User $user): bool
    {
        return $user->isActive() && ($user->can('user.manage') || $user->can('role.manage'));
    }

    public function viewAny(User $user): bool { return $this->allowed($user); }
    public function view(User $user, User $target): bool { return $this->allowed($user) || ($user->id === $target->id && $user->isActive()); }
    public function create(User $user): bool { return $this->allowed($user); }
    public function update(User $user, User $target): bool { return $this->allowed($user); }
    public function delete(User $user, User $target): bool { return $this->allowed($user) && $user->id !== $target->id; }
}
