<?php

namespace App\Actions\Biometrics;

use App\Models\Employee;
use App\Models\EmployeeBiometricTemplate;
use App\Models\Meeting;
use App\Models\MeetingParticipantVersionMember;
use App\Models\MeetingTerminalAssignment;
use App\Models\Terminal;
use App\Models\TerminalMeetingSession;
use App\Support\BiometricFingerPosition;
use App\Support\MeetingOrganizationResolver;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EnrollMeetingParticipantFingerprintTemplate
{
    public function __construct(
        private TerminalAuditLogger $audit,
        private MeetingOrganizationResolver $organizations,
    ) {}

    public function execute(TerminalMeetingSession $session, array $data): array
    {
        return DB::transaction(function () use ($session, $data): array {
            // Keep the FLOW-09 lock order: Terminal -> Meeting -> Assignment/Session.
            $terminal = Terminal::query()->lockForUpdate()->findOrFail($session->terminal_id);
            $meeting = Meeting::query()->with('units.unit')->lockForUpdate()->findOrFail($session->meeting_id);
            $assignment = MeetingTerminalAssignment::query()->lockForUpdate()->findOrFail($session->assignment_id);
            $lockedSession = TerminalMeetingSession::query()->lockForUpdate()->findOrFail($session->id);

            if ((int) $assignment->meeting_id !== (int) $meeting->id
                || (int) $assignment->terminal_id !== (int) $terminal->id
                || (int) $lockedSession->meeting_id !== (int) $meeting->id
                || (int) $lockedSession->terminal_id !== (int) $terminal->id
                || (int) $lockedSession->assignment_id !== (int) $assignment->id) {
                $this->fail('TERMINAL_SESSION_CONTEXT_INVALID', 404, 'نشست ترمینال برای این جلسه معتبر نیست.');
            }

            if (! $lockedSession->isEffectivelyActive() || $terminal->status !== 'ACTIVE') {
                $this->fail('TERMINAL_SESSION_INACTIVE', 401, 'نشست فعال ترمینال یافت نشد.');
            }

            if (! in_array($meeting->status, ['SCHEDULED', 'IN_PROGRESS'], true)) {
                $this->fail('MEETING_NOT_ATTENDANCE_ELIGIBLE', 422, 'ثبت اثر انگشت فقط برای جلسهٔ برنامه‌ریزی‌شده یا در حال برگزاری ممکن است.');
            }

            $stale = $assignment->staleness($meeting);
            if ($assignment->status !== 'ASSIGNED'
                || ! $assignment->isWindowOpen()
                || $stale['schedule_changed']
                || $stale['participants_changed']
                || (int) $lockedSession->schedule_version_id !== (int) $meeting->current_schedule_version_id
                || (int) $lockedSession->participant_version_id !== (int) $meeting->current_participant_version_id
                || (int) $lockedSession->participant_version_id !== (int) $assignment->baseline_participant_version_id) {
                $this->fail('TERMINAL_SESSION_STALE', 422, 'تخصیص ترمینال یا فهرست اشتراک‌کنندگان دیگر معتبر نیست؛ اطلاعات جلسه را تازه‌سازی کنید.');
            }

            if (! BiometricFingerPosition::isValid((string) $data['finger_position'])) {
                $this->fail('FINGER_POSITION_REQUIRED', 422, 'موقعیت انگشت معتبر نیست.');
            }
            if (! ($data['quality_accepted'] ?? true)) {
                $this->fail('FINGERPRINT_QUALITY_TOO_LOW', 422, 'کیفیت نمونهٔ اثر انگشت پایین است.');
            }
            if ($data['replace_active'] ?? false) {
                $this->fail('BIOMETRIC_TEMPLATE_REPLACEMENT_NOT_ALLOWED', 403, 'جایگزینی اثر انگشت از مسیر حضور جلسه مجاز نیست.');
            }

            $templateData = (string) $data['template_data'];
            if (($data['template_encoding'] ?? 'RAW_UTF8') === 'BASE64') {
                $decoded = base64_decode($templateData, true);
                if ($decoded === false) {
                    $this->fail('BIOMETRIC_TEMPLATE_ENCODING_INVALID', 422, 'قالب اثر انگشت رمزگذاری معتبر ندارد.');
                }
                $templateData = $decoded;
            }
            if ($templateData === '') {
                $this->fail('BIOMETRIC_TEMPLATE_MISSING', 422, 'قالب اثر انگشت ارسال نشده است.');
            }
            if (str_contains($data['template_vendor'].$data['template_format'].$data['algorithm_version'], "\0")) {
                $this->fail('BIOMETRIC_TEMPLATE_FORMAT_UNSUPPORTED', 422, 'مشخصات قالب اثر انگشت معتبر نیست.');
            }

            $employee = Employee::query()->with('unit')->lockForUpdate()->findOrFail((int) $data['employee_id']);
            if ($employee->status !== 'ACTIVE') {
                $this->fail('EMPLOYEE_NOT_BIOMETRIC_ENROLLABLE', 422, 'کارمند فعال نیست.');
            }

            $isParticipant = MeetingParticipantVersionMember::query()
                ->where('participant_version_id', $lockedSession->participant_version_id)
                ->where('employee_id', $employee->id)
                ->exists();
            if (! $isParticipant) {
                $this->fail('BIOMETRIC_MATCH_EMPLOYEE_NOT_PARTICIPANT', 403, 'این کارمند در فهرست رسمی اشتراک‌کنندگان جلسه نیست.');
            }

            $meetingOrganizationId = $this->organizations->id($meeting);
            if (! $employee->unit?->organization_id
                || (int) $employee->unit->organization_id !== (int) $terminal->organization_id
                || (int) $terminal->organization_id !== (int) $meetingOrganizationId) {
                $this->fail('BIOMETRIC_ENROLLMENT_TERMINAL_SCOPE_MISMATCH', 403, 'ترمینال و کارمند در محدودهٔ سازمانی این جلسه نیستند.');
            }

            $activeTemplates = EmployeeBiometricTemplate::query()
                ->where('employee_id', $employee->id)
                ->where('status', EmployeeBiometricTemplate::ACTIVE)
                ->lockForUpdate()
                ->get();
            if ($activeTemplates->contains('finger_position', $data['finger_position'])) {
                $this->fail('BIOMETRIC_TEMPLATE_STALE', 409, 'برای این انگشت قالب فعال وجود دارد؛ جایگزینی فقط از روند رسمی ثبت بایومتریک ممکن است.');
            }

            $required = max(1, (int) config('biometric.minimum_active_fingers', 2));
            if ($activeTemplates->pluck('finger_position')->unique()->count() >= $required) {
                $this->fail('MEETING_BIOMETRIC_ENROLLMENT_COMPLETE', 409, 'حداقل اثرانگشت‌های لازم برای این کارمند قبلاً ثبت شده است.');
            }

            $version = ((int) EmployeeBiometricTemplate::query()
                ->where('employee_id', $employee->id)
                ->where('finger_position', $data['finger_position'])
                ->lockForUpdate()
                ->max('version_number')) + 1;
            $checksum = hash('sha256', $templateData);

            $template = EmployeeBiometricTemplate::create([
                'uuid' => (string) Str::uuid(),
                'organization_id' => $employee->unit->organization_id,
                'employee_id' => $employee->id,
                'modality' => 'FINGERPRINT',
                'finger_position' => $data['finger_position'],
                'template_vendor' => $data['template_vendor'],
                'template_format' => $data['template_format'],
                'algorithm_version' => $data['algorithm_version'],
                'template_data' => $templateData,
                'template_checksum' => $checksum,
                'quality_score' => $data['quality_score'] ?? null,
                'version_number' => $version,
                'status' => EmployeeBiometricTemplate::ACTIVE,
                'source_terminal_id' => $terminal->id,
                'terminal_meeting_session_id' => $lockedSession->id,
                'enrollment_session_id' => null,
                'enrolled_by_user_id' => null,
                'enrolled_at' => now(),
            ]);

            $activePositions = $activeTemplates->pluck('finger_position')->push($data['finger_position'])->unique()->values();
            $this->audit->record('MEETING_BIOMETRIC_TEMPLATE_ENROLLED', null, $terminal, $meeting, $assignment, $lockedSession, [
                'authorization_source' => 'ACTIVE_TERMINAL_MEETING_SESSION',
                'participant_version_id' => $lockedSession->participant_version_id,
                'employee_id' => $employee->id,
                'template_uuid' => $template->uuid,
                'finger_position' => $template->finger_position,
                'version_number' => $version,
                'template_checksum' => $checksum,
            ]);

            return [
                'template' => $template->fresh(),
                'progress' => [
                    'employee_id' => (int) $employee->id,
                    'active_finger_count' => $activePositions->count(),
                    'required_finger_count' => $required,
                    'active_finger_positions' => $activePositions->all(),
                ],
            ];
        });
    }

    private function fail(string $code, int $status, string $message): never
    {
        abort(response()->json(['success' => false, 'code' => $code, 'message' => $message, 'errors' => []], $status));
    }
}
