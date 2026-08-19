<?php

namespace App\Actions\Biometrics;

use App\Models\TerminalMeetingSession;
use App\Support\BiometricCandidateSetBuilder;

class BuildMeetingBiometricCandidateSet
{
    public function __construct(private BiometricCandidateSetBuilder $builder) {}

    public function execute(TerminalMeetingSession $session): array
    {
        return $this->builder->build($session);
    }
}
