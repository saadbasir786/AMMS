<?php

namespace App\Support;

final class BiometricFingerPosition
{
    public const ALL = [
        'RIGHT_THUMB', 'RIGHT_INDEX', 'RIGHT_MIDDLE', 'RIGHT_RING', 'RIGHT_LITTLE',
        'LEFT_THUMB', 'LEFT_INDEX', 'LEFT_MIDDLE', 'LEFT_RING', 'LEFT_LITTLE',
    ];

    private function __construct() {}

    public static function isValid(string $value): bool
    {
        return in_array($value, self::ALL, true);
    }
}
