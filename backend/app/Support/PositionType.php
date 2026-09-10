<?php

declare(strict_types=1);

namespace App\Support;

final class PositionType
{
    public const DIRECTOR = 'DIRECTOR';
    public const DEPUTY_DIRECTOR = 'DEPUTY_DIRECTOR';
    public const AMIR = 'AMIR';
    public const GENERAL_MANAGER = 'GENERAL_MANAGER';
    public const EXPERT = 'EXPERT';
    public const MANAGER = 'MANAGER';
    public const EMPLOYEE = 'EMPLOYEE';
    public const EXECUTIVE_MANAGEMENT = 'EXECUTIVE_MANAGEMENT';
    public const HIGH_AUTHORITY = 'HIGH_AUTHORITY';
    public const AUTHORITY_OFFICE = 'AUTHORITY_OFFICE';
    public const EXECUTIVE_SECRETARY = 'EXECUTIVE_SECRETARY';
    public const OTHER = 'OTHER';

    public const ALL = [
        self::DIRECTOR,
        self::DEPUTY_DIRECTOR,
        self::AMIR,
        self::GENERAL_MANAGER,
        self::EXPERT,
        self::MANAGER,
        self::EMPLOYEE,
        self::EXECUTIVE_MANAGEMENT,
        self::HIGH_AUTHORITY,
        self::AUTHORITY_OFFICE,
        self::EXECUTIVE_SECRETARY,
        self::OTHER,
    ];

    public const SENIORITY_LEVELS = [
        self::DIRECTOR => 100,
        self::DEPUTY_DIRECTOR => 90,
        self::AMIR => 80,
        self::GENERAL_MANAGER => 70,
        self::EXPERT => 70,
        self::MANAGER => 60,
        self::EMPLOYEE => 50,
    ];

    public static function all(): array
    {
        return self::ALL;
    }

    public static function seniority(): array
    {
        return self::SENIORITY_LEVELS;
    }

    public static function isValid(?string $type): bool
    {
        return $type === null || in_array($type, self::ALL, true);
    }

    public static function labels(): array
    {
        return [
            self::DIRECTOR => 'رییس',
            self::DEPUTY_DIRECTOR => 'معاون رییس',
            self::AMIR => 'آمر',
            self::GENERAL_MANAGER => 'مدیر عمومی',
            self::EXPERT => 'کارشناس',
            self::MANAGER => 'مدیر',
            self::EMPLOYEE => 'کارمند',
            self::EXECUTIVE_MANAGEMENT => 'مدیریت اجرایی',
            self::HIGH_AUTHORITY => 'مقام عالی',
            self::AUTHORITY_OFFICE => 'دفتر مقام',
            self::EXECUTIVE_SECRETARY => 'منشی اجرایی',
            self::OTHER => 'سایر بست‌ها',
        ];
    }
}
