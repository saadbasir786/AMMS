<?php

namespace App\Actions\Biometrics;

use App\Models\BiometricEnrollmentSession;
use App\Models\Employee;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use App\Models\User;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StartBiometricEnrollment
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function execute(User $actor, Employee $employee, ?Terminal $terminal = null, ?int $requiredFingerCount = null): BiometricEnrollmentSession
    {
        return DB::transaction(function () use ($actor, $employee, $terminal, $requiredFingerCount): BiometricEnrollmentSession {
            $lockedEmployee = Employee::query()->with('unit')->lockForUpdate()->findOrFail($employee->id);
            if ($lockedEmployee->status !== 'ACTIVE') {
                $this->fail('EMPLOYEE_NOT_BIOMETRIC_ENROLLABLE', 422, 'کارمند فعال نیست.');
            }

            $lockedTerminal = null;
            if ($terminal !== null) {
                $lockedTerminal = Terminal::query()->lockForUpdate()->findOrFail($terminal->id);
                if ($lockedTerminal->status !== 'ACTIVE') {
                    $this->fail('TERMINAL_NOT_AVAILABLE', 422, 'ترمینال برای ثبت بایومتریک آماده نیست.');
                }

                $meetingSession = TerminalMeetingSession::query()
                    ->where('terminal_id', $lockedTerminal->id)
                    ->where('status', 'ACTIVE')
                    ->lockForUpdate()
                    ->first();
                if ($meetingSession !== null && !$meetingSession->isExpired()) {
                    $this->fail('TERMINAL_BUSY', 422, 'ترمینال در نشست جلسه استفاده می‌شود.');
                }
                if ($meetingSession?->isExpired()) {
                    $meetingSession->forceFill(['status' => 'EXPIRED'])->saveQuietly();
                }
            }

            $activeEmployeeSession = BiometricEnrollmentSession::query()
                ->where('employee_id', $lockedEmployee->id)
                ->active()
                ->lockForUpdate()
                ->first();
            if ($activeEmployeeSession !== null) {
                if ($activeEmployeeSession->isExpired()) {
                    $activeEmployeeSession->forceFill(['status' => BiometricEnrollmentSession::EXPIRED])->saveQuietly();
                } else {
                    $this->fail('BIOMETRIC_ENROLLMENT_ALREADY_ACTIVE', 422, 'برای این کارمند ثبت بایومتریک فعال است.');
                }
            }

            if ($lockedTerminal !== null) {
                $activeTerminalSession = BiometricEnrollmentSession::query()
                    ->where('terminal_id', $lockedTerminal->id)
                    ->active()
                    ->lockForUpdate()
                    ->first();
                if ($activeTerminalSession !== null) {
                    if ($activeTerminalSession->isExpired()) {
                        $activeTerminalSession->forceFill(['status' => BiometricEnrollmentSession::EXPIRED])->saveQuietly();
                    } else {
                        $this->fail('BIOMETRIC_ENROLLMENT_ALREADY_ACTIVE', 422, 'این ترمینال در ثبت بایومتریک دیگری استفاده می‌شود.');
                    }
                }
            }

            $required = max(1, $requiredFingerCount ?? (int) config('biometric.minimum_active_fingers', 2));
            $session = BiometricEnrollmentSession::create([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $lockedEmployee->unit?->organization_id,
                'employee_id' => $lockedEmployee->id,
                'terminal_id' => $lockedTerminal?->id,
                'started_by_user_id' => $actor->id,
                'status' => BiometricEnrollmentSession::PENDING_CAPTURE,
                'required_finger_count' => $required,
                'started_at' => now(),
                'expires_at' => now()->addMinutes((int) config('biometric.enrollment_session_ttl_minutes', 15)),
            ]);

            $this->audit->record('BIOMETRIC_ENROLLMENT_STARTED', $actor, $lockedTerminal, null, null, null, [
                'enrollment_session_uuid' => $session->uuid,
                'employee_id' => $lockedEmployee->id,
                'required_finger_count' => $required,
            ]);

            return $session->fresh(['employee', 'terminal', 'startedBy']);
        });
    }

    private function fail(string $code, int $status, string $message): never
    {
        abort(response()->json(['success' => false, 'code' => $code, 'message' => $message, 'errors' => []], $status));
    }
}
