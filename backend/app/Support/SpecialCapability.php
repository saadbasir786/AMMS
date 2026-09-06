<?php

namespace App\Support;

use InvalidArgumentException;

final class SpecialCapability
{
    public const BIOMETRIC_ENROLLMENT = 'BIOMETRIC_ENROLLMENT';
    public const INTERNAL_MEETING_REQUEST_APPROVAL = 'INTERNAL_MEETING_REQUEST_APPROVAL';

    public static function all(): array
    {
        return [
            self::BIOMETRIC_ENROLLMENT,
            self::INTERNAL_MEETING_REQUEST_APPROVAL,
        ];
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
            self::INTERNAL_MEETING_REQUEST_APPROVAL => [
                'meeting-request.approve-delegated',
            ],
            default => throw new InvalidArgumentException("Unknown AMMS special capability: {$capability}"),
        };
    }

    public static function systemAdminAssignable(): array
    {
        return [self::BIOMETRIC_ENROLLMENT];
    }
}
