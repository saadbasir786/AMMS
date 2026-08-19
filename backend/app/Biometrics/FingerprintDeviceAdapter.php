<?php

namespace App\Biometrics;

interface FingerprintDeviceAdapter
{
    public function isAvailable(): bool;
    public function getCapabilities(): array;
    public function startEnrollmentCapture(array $context): array;
    public function cancelEnrollmentCapture(): void;
    public function extractTemplate(string $capture): string;
    public function getQuality(string $capture): array;
    public function identify(string $probe, array $candidates): IdentificationResult;
    public function clearTemporaryData(): void;
}
