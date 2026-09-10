<?php

declare(strict_types=1);

namespace App\Support;

final class RolePositionCompatibility
{
    public static function matches(string $role, ?string $positionType): bool
    {
        if (! AmmsRole::isValid($role) || $role === AmmsRole::SYSTEM_ADMIN) {
            return false;
        }

        if ($role === AmmsRole::EMPLOYEE) {
            return true;
        }

        if ($role === AmmsRole::EXECUTIVE_MANAGEMENT) {
            return in_array($positionType, [PositionType::EXECUTIVE_MANAGEMENT, PositionType::GENERAL_MANAGER], true);
        }

        return $positionType === $role;
    }
}
