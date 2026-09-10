<?php

declare(strict_types=1);

namespace App\Support;

final class OrganizationalUnitType
{
    public const GENERAL_PRESIDENCY = 'GENERAL_PRESIDENCY';
    public const DEPUTY_PRESIDENCY = 'DEPUTY_PRESIDENCY';
    public const GENERAL_DIRECTORATE = 'GENERAL_DIRECTORATE';
    public const DIRECTORATE = 'DIRECTORATE';
    public const ATTACHED_AGENCY = 'ATTACHED_AGENCY';
    public const DEPUTY_DIRECTORATE = 'DEPUTY_DIRECTORATE';
    public const AMIRIATE = 'AMIRIATE';
    public const GENERAL_MANAGEMENT = 'GENERAL_MANAGEMENT';
    public const MANAGEMENT = 'MANAGEMENT';
    public const SECTION = 'SECTION';
    public const OTHER = 'OTHER';

    // Persisted compatibility values retained for existing data and contracts.
    public const DEPARTMENT = 'DEPARTMENT';
    public const OFFICE = 'OFFICE';
    public const HIGH_AUTHORITY = 'HIGH_AUTHORITY';
    public const AUTHORITY_OFFICE = 'AUTHORITY_OFFICE';

    public const FORMAL_GOVERNANCE = [
        self::GENERAL_PRESIDENCY,
        self::DEPUTY_PRESIDENCY,
        self::GENERAL_DIRECTORATE,
        self::DIRECTORATE,
        self::ATTACHED_AGENCY,
        self::DEPUTY_DIRECTORATE,
    ];

    public const ALL = [
        self::GENERAL_PRESIDENCY,
        self::DEPUTY_PRESIDENCY,
        self::GENERAL_DIRECTORATE,
        self::DIRECTORATE,
        self::ATTACHED_AGENCY,
        self::DEPUTY_DIRECTORATE,
        self::AMIRIATE,
        self::GENERAL_MANAGEMENT,
        self::MANAGEMENT,
        self::SECTION,
        self::OTHER,
        self::DEPARTMENT,
        self::OFFICE,
        self::HIGH_AUTHORITY,
        self::AUTHORITY_OFFICE,
    ];

    public static function all(): array
    {
        return self::ALL;
    }

    public static function formalGovernance(): array
    {
        return self::FORMAL_GOVERNANCE;
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    public static function isFormalGovernance(string $type): bool
    {
        return in_array($type, self::formalGovernance(), true);
    }

    public static function labels(): array
    {
        return [
            self::GENERAL_PRESIDENCY => 'ریاست عمومی',
            self::DEPUTY_PRESIDENCY => 'معاونیت ریاست عمومی',
            self::GENERAL_DIRECTORATE => 'ریاست عمومی',
            self::DIRECTORATE => 'ریاست',
            self::ATTACHED_AGENCY => 'ادارهٔ تابعه',
            self::DEPUTY_DIRECTORATE => 'معاونیت ریاست',
            self::AMIRIATE => 'آمریت',
            self::GENERAL_MANAGEMENT => 'مدیریت عمومی',
            self::MANAGEMENT => 'مدیریت',
            self::SECTION => 'شعبه',
            self::OTHER => 'سایر واحدها',
            self::DEPARTMENT => 'دیپارتمنت',
            self::OFFICE => 'دفتر',
            self::HIGH_AUTHORITY => 'مقام عالی',
            self::AUTHORITY_OFFICE => 'دفتر مقام',
        ];
    }
}
