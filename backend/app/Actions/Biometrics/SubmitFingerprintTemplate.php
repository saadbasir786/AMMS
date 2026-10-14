<?php

namespace App\Actions\Biometrics;

use App\Models\BiometricEnrollmentSession;
use App\Models\EmployeeBiometricTemplate;
use App\Models\User;
use App\Policies\BiometricPolicy;
use App\Support\BiometricFingerPosition;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubmitFingerprintTemplate
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function execute(
        User $actor,
        BiometricEnrollmentSession $enrollment,
        string $fingerPosition,
        string $templateVendor,
        string $templateFormat,
        string $algorithmVersion,
        string $templateData,
        ?float $qualityScore = null,
        bool $qualityAccepted = true,
        bool $replaceActive = false,
        string $templateEncoding = 'RAW_UTF8',
    ): EmployeeBiometricTemplate {
        return DB::transaction(function () use ($actor, $enrollment, $fingerPosition, $templateVendor, $templateFormat, $algorithmVersion, $templateData, $templateEncoding, $qualityScore, $qualityAccepted, $replaceActive): EmployeeBiometricTemplate {
            if ($templateEncoding === 'BASE64') {
                $decoded = base64_decode($templateData, true);
                if ($decoded === false) {
                    $this->fail('BIOMETRIC_TEMPLATE_ENCODING_INVALID', 422, 'قالب بایومتریک رمزگذاری معتبر ندارد.');
                }
                $templateData = $decoded;
            }
            $session = BiometricEnrollmentSession::query()
                ->with(['employee.unit', 'terminal'])
                ->lockForUpdate()
                ->findOrFail($enrollment->id);

            $currentActor = User::query()->lockForUpdate()->findOrFail($actor->id);
            // Terminal capture may happen minutes after a human operator opened the enrollment.
            // Re-check current authority before accepting machine-submitted template bytes so a
            // revoked operator cannot continue an already-open terminal enrollment session.
            if ($session->terminal_id !== null && !app(BiometricPolicy::class)->enroll($currentActor, $session->employee)) {
                $this->fail('BIOMETRIC_ENROLLMENT_AUTHORITY_REVOKED', 403, 'صلاحیت ثبت بایومتریک این نشست دیگر معتبر نیست.');
            }

            if (!$session->isActive()) {
                if ($session->isExpired() && $session->status !== BiometricEnrollmentSession::EXPIRED) {
                    $session->forceFill(['status' => BiometricEnrollmentSession::EXPIRED])->saveQuietly();
                }
                $this->fail('BIOMETRIC_ENROLLMENT_SESSION_EXPIRED', 422, 'نشست ثبت بایومتریک منقضی یا غیرفعال است.');
            }
            if (!BiometricFingerPosition::isValid($fingerPosition)) {
                $this->fail('FINGER_POSITION_REQUIRED', 422, 'موقعیت انگشت معتبر نیست.');
            }
            if (!$qualityAccepted) {
                $this->fail('FINGERPRINT_QUALITY_TOO_LOW', 422, 'کیفیت نمونهٔ بایومتریک پایین است.');
            }
            if ($templateData === '') {
                $this->fail('BIOMETRIC_TEMPLATE_MISSING', 422, 'قالب بایومتریک ارسال نشده است.');
            }
            if (str_contains($templateVendor . $templateFormat . $algorithmVersion, "\0")) {
                $this->fail('BIOMETRIC_TEMPLATE_FORMAT_UNSUPPORTED', 422, 'مشخصات قالب بایومتریک معتبر نیست.');
            }

            $employeeLock = $session->employee()->lockForUpdate()->firstOrFail();
            $active = EmployeeBiometricTemplate::query()
                ->where('employee_id', $employeeLock->id)
                ->where('finger_position', $fingerPosition)
                ->where('status', EmployeeBiometricTemplate::ACTIVE)
                ->lockForUpdate()
                ->first();

            if ($active !== null && !$replaceActive) {
                $this->fail('BIOMETRIC_TEMPLATE_STALE', 422, 'برای این انگشت قالب فعال موجود است؛ ثبت مجدد باید صریح باشد.');
            }

            $nextVersion = ((int) EmployeeBiometricTemplate::query()
                ->where('employee_id', $employeeLock->id)
                ->where('finger_position', $fingerPosition)
                ->lockForUpdate()
                ->max('version_number')) + 1;

            $template = EmployeeBiometricTemplate::create([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $session->organization_id,
                'employee_id' => $employeeLock->id,
                'modality' => 'FINGERPRINT',
                'finger_position' => $fingerPosition,
                'template_vendor' => $templateVendor,
                'template_format' => $templateFormat,
                'algorithm_version' => $algorithmVersion,
                'template_data' => $templateData,
                'template_checksum' => hash('sha256', $templateData),
                'quality_score' => $qualityScore,
                'version_number' => $nextVersion,
                'status' => EmployeeBiometricTemplate::ACTIVE,
                'source_terminal_id' => $session->terminal_id,
                'enrollment_session_id' => $session->id,
                'enrolled_by_user_id' => $currentActor->id,
                'enrolled_at' => now(),
            ]);

            if ($active !== null) {
                $active->forceFill([
                    'status' => EmployeeBiometricTemplate::SUPERSEDED,
                    'superseded_at' => now(),
                ])->saveQuietly();
                $this->audit->record('BIOMETRIC_TEMPLATE_SUPERSEDED', $currentActor, $session->terminal, null, null, null, [
                    'employee_id' => $employeeLock->id,
                    'template_uuid' => $active->uuid,
                    'finger_position' => $fingerPosition,
                    'version_number' => $active->version_number,
                ]);
            }

            $session->forceFill(['status' => BiometricEnrollmentSession::IN_PROGRESS])->saveQuietly();
            $this->audit->record('BIOMETRIC_TEMPLATE_ENROLLED', $currentActor, $session->terminal, null, null, null, [
                'enrollment_session_uuid' => $session->uuid,
                'employee_id' => $employeeLock->id,
                'template_uuid' => $template->uuid,
                'finger_position' => $fingerPosition,
                'version_number' => $nextVersion,
                'template_checksum' => $template->template_checksum,
                'quality_score' => $qualityScore,
            ]);

            return $template->fresh(['employee', 'sourceTerminal', 'enrollmentSession', 'enrolledBy']);
        });
    }

    private function fail(string $code, int $status, string $message): never
    {
        abort(response()->json(['success' => false, 'code' => $code, 'message' => $message, 'errors' => []], $status));
    }
}
