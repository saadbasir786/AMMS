<?php

declare(strict_types=1);

namespace App\Support;

final class AmmsRole
{
    public const SYSTEM_ADMIN = 'SYSTEM_ADMIN';
    public const EMPLOYEE = 'EMPLOYEE';
    public const DIRECTOR = 'DIRECTOR';
    public const DEPUTY_DIRECTOR = 'DEPUTY_DIRECTOR';
    public const EXECUTIVE_MANAGEMENT = 'EXECUTIVE_MANAGEMENT';
    public const AUTHORITY_OFFICE = 'AUTHORITY_OFFICE';
    public const EXECUTIVE_SECRETARY = 'EXECUTIVE_SECRETARY';
    public const HIGH_AUTHORITY = 'HIGH_AUTHORITY';

    public static function all(): array
    {
        return [
            self::SYSTEM_ADMIN,
            self::EMPLOYEE,
            self::DIRECTOR,
            self::DEPUTY_DIRECTOR,
            self::EXECUTIVE_MANAGEMENT,
            self::AUTHORITY_OFFICE,
            self::EXECUTIVE_SECRETARY,
            self::HIGH_AUTHORITY,
        ];
    }

    public static function businessRoles(): array
    {
        return array_values(array_diff(self::all(), [self::SYSTEM_ADMIN]));
    }

    public static function scopedRoles(): array
    {
        return self::businessRoles();
    }

    public static function unscopedRoles(): array
    {
        return [self::SYSTEM_ADMIN];
    }

    public static function isValid(string $role): bool
    {
        return in_array($role, self::all(), true);
    }

    public static function labels(): array
    {
        return [
            self::SYSTEM_ADMIN => 'مدیر سیستم (فنی)',
            self::EMPLOYEE => 'کارمند',
            self::DIRECTOR => 'رییس اداره',
            self::DEPUTY_DIRECTOR => 'معاون اداره',
            self::EXECUTIVE_MANAGEMENT => 'مدیریت اجرایی',
            self::AUTHORITY_OFFICE => 'دفتر مقام عالی',
            self::EXECUTIVE_SECRETARY => 'منشی مقام عالی',
            self::HIGH_AUTHORITY => 'مقام عالی',
        ];
    }
}
