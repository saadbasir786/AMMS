<?php

namespace App\Biometrics;

final class IdentificationResult
{
    public function __construct(
        public readonly bool $matched,
        public readonly ?int $employeeId = null,
        public readonly ?string $matchedTemplateUuid = null,
        public readonly ?string $fingerPosition = null,
        public readonly ?string $matcherVendor = null,
        public readonly ?string $matcherAlgorithmVersion = null,
        public readonly ?float $matchScore = null,
        public readonly ?string $thresholdReference = null,
        public readonly string $outcome = 'NO_MATCH',
    ) {}
}
