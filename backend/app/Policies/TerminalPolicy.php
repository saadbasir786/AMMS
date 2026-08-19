<?php

namespace App\Policies;

use App\Models\Terminal;
use App\Models\User;

class TerminalPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdminWith($user, 'terminal.view');
    }

    public function view(User $user, Terminal $terminal): bool
    {
        return $this->isAdminWith($user, 'terminal.view');
    }

    public function register(User $user): bool
    {
        return $this->isAdminWith($user, 'terminal.register');
    }

    public function manage(User $user, Terminal $terminal): bool
    {
        return $this->isAdminWith($user, 'terminal.manage');
    }

    public function rotateCredential(User $user, Terminal $terminal): bool
    {
        return $this->isAdminWith($user, 'terminal.rotate-credential');
    }

    public function revoke(User $user, Terminal $terminal): bool
    {
        return $this->isAdminWith($user, 'terminal.revoke');
    }

    private function isAdminWith(User $user, string $permission): bool
    {
        return $user->isActive() && $user->can($permission) && $user->hasScopedRole('SYSTEM_ADMIN');
    }
}
