<?php

namespace App\Actions\Biometrics;

use App\Models\BiometricEnrollmentSession;
use App\Models\User;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;

class CancelBiometricEnrollment
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function execute(User $actor, BiometricEnrollmentSession $enrollment, string $reason): BiometricEnrollmentSession
    {
        return DB::transaction(function () use ($actor, $enrollment, $reason): BiometricEnrollmentSession {
            $session = BiometricEnrollmentSession::query()->with(['employee', 'terminal'])->lockForUpdate()->findOrFail($enrollment->id);
            if (!$session->isActive()) {
                abort(response()->json(['success' => false, 'code' => 'BIOMETRIC_ENROLLMENT_SESSION_EXPIRED', 'message' => 'نشست ثبت بایومتریک فعال نیست.', 'errors' => []], 422));
            }
            $session->forceFill(['status' => BiometricEnrollmentSession::CANCELLED, 'cancelled_at' => now(), 'cancel_reason' => $reason])->saveQuietly();
            $this->audit->record('BIOMETRIC_ENROLLMENT_CANCELLED', $actor, $session->terminal, null, null, null, [
                'enrollment_session_uuid' => $session->uuid,
                'employee_id' => $session->employee_id,
                'reason' => $reason,
            ]);
            return $session->fresh(['employee', 'terminal', 'startedBy']);
        });
    }
}
