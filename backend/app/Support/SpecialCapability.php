<?php

namespace App\Support;

use InvalidArgumentException;

final class SpecialCapability
{
    public const BIOMETRIC_ENROLLMENT = 'BIOMETRIC_ENROLLMENT';

    public static function all(): array
    {
        return [self::BIOMETRIC_ENROLLMENT];
    }

    public static function permissions(string $capability): array
    {
        return match ($capability) {
            self::BIOMETRIC_ENROLLMENT => [
                'biometric.status.view',
                'biometric.enroll',
                'biometric.reenroll',
                'biometric.revoke',
                'biometric.enrollment.cancel',
            ],
            default => throw new InvalidArgumentException("Unknown AMMS special capability: {$capability}"),
        };
    }
}
