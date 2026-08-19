<?php

namespace App\Biometrics;

final class FakeFingerprintAdapter implements FingerprintDeviceAdapter
{
    public function isAvailable(): bool { return true; }

    public function getCapabilities(): array
    {
        return ['driver' => 'fake', 'simulated' => true, 'capture' => true, 'template_extraction' => true, 'identification_1_to_n' => true, 'quality' => true];
    }

    public function startEnrollmentCapture(array $context): array
    {
        return ['capture_fixture' => 'FAKE_CAPTURE_' . ($context['finger_position'] ?? 'UNKNOWN')];
    }

    public function cancelEnrollmentCapture(): void {}

    public function extractTemplate(string $capture): string
    {
        return 'FAKE_TEMPLATE_' . hash('sha256', $capture);
    }

    public function getQuality(string $capture): array
    {
        return str_contains($capture, 'LOW') ? ['quality_score' => 10.0, 'quality_accepted' => false, 'outcome' => 'LOW'] : ['quality_score' => 95.0, 'quality_accepted' => true, 'outcome' => 'GOOD'];
    }

    public function identify(string $probe, array $candidates): IdentificationResult
    {
        if ($probe === 'DEVICE_UNAVAILABLE') return new IdentificationResult(false, outcome: 'DEVICE_UNAVAILABLE');
        if ($probe === 'CAPTURE_CANCELLED') return new IdentificationResult(false, outcome: 'CAPTURE_CANCELLED');
        if ($probe === 'LOW_SCORE') return new IdentificationResult(false, outcome: 'BELOW_THRESHOLD');
        if ($probe === 'UNKNOWN') return new IdentificationResult(false, outcome: 'NO_MATCH');
        foreach ($candidates as $candidate) {
            if (($candidate['fake_probe_fixture'] ?? null) === $probe) {
                return new IdentificationResult(true, (int) $candidate['employee_id'], $candidate['template_uuid'], $candidate['finger_position'], 'FAKE', 'SIMULATOR-V1', 99.0, 'FAKE_ACCEPTED', 'MATCH');
            }
        }
        return new IdentificationResult(false, outcome: 'NO_MATCH');
    }

    public function clearTemporaryData(): void {}
}
