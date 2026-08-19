<?php

namespace App\Actions\Biometrics;

use App\Models\BiometricEnrollmentSession;
use App\Models\EmployeeBiometricTemplate;
use App\Models\User;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;

class CompleteBiometricEnrollment
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function execute(User $actor, BiometricEnrollmentSession $enrollment): BiometricEnrollmentSession
    {
        return DB::transaction(function () use ($actor, $enrollment): BiometricEnrollmentSession {
            $session = BiometricEnrollmentSession::query()->with(['employee', 'terminal'])->lockForUpdate()->findOrFail($enrollment->id);
            if (!$session->isActive()) {
                $this->fail('BIOMETRIC_ENROLLMENT_SESSION_EXPIRED', 422, 'نشست ثبت بایومتریک منقضی یا غیرفعال است.');
            }
            $count = EmployeeBiometricTemplate::query()
                ->where('employee_id', $session->employee_id)
                ->where('status', EmployeeBiometricTemplate::ACTIVE)
                ->distinct('finger_position')
                ->count('finger_position');
            if ($count < $session->required_finger_count) {
                $this->fail('BIOMETRIC_MINIMUM_FINGERS_REQUIRED', 422, 'تعداد انگشت‌های فعال برای تکمیل ثبت کافی نیست.');
            }

            $session->forceFill(['status' => BiometricEnrollmentSession::COMPLETED, 'completed_at' => now()])->saveQuietly();
            $this->audit->record('BIOMETRIC_ENROLLMENT_COMPLETED', $actor, $session->terminal, null, null, null, [
                'enrollment_session_uuid' => $session->uuid,
                'employee_id' => $session->employee_id,
                'active_finger_count' => $count,
            ]);
            return $session->fresh(['employee', 'terminal', 'startedBy']);
        });
    }

    private function fail(string $code, int $status, string $message): never
    {
        abort(response()->json(['success' => false, 'code' => $code, 'message' => $message, 'errors' => []], $status));
    }
}
