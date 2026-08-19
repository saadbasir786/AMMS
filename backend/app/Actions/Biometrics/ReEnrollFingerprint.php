<?php

namespace App\Actions\Biometrics;

use App\Models\BiometricEnrollmentSession;
use App\Models\EmployeeBiometricTemplate;
use App\Models\User;
use App\Support\TerminalAuditLogger;

class ReEnrollFingerprint
{
    public function __construct(private SubmitFingerprintTemplate $submit, private TerminalAuditLogger $audit) {}

    public function execute(User $actor, BiometricEnrollmentSession $enrollment, string $fingerPosition, string $templateVendor, string $templateFormat, string $algorithmVersion, string $templateData, ?float $qualityScore = null, bool $qualityAccepted = true): EmployeeBiometricTemplate
    {
        $this->audit->record('BIOMETRIC_REENROLLMENT_STARTED', $actor, $enrollment->terminal, null, null, null, [
            'enrollment_session_uuid' => $enrollment->uuid,
            'employee_id' => $enrollment->employee_id,
            'finger_position' => $fingerPosition,
        ]);
        return $this->submit->execute($actor, $enrollment, $fingerPosition, $templateVendor, $templateFormat, $algorithmVersion, $templateData, $qualityScore, $qualityAccepted, true);
    }
}
