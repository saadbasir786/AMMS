<?php

namespace App\Actions\Attendance;

use App\Models\EmployeeBiometricTemplate;
use App\Models\MeetingAttendanceRecord;
use App\Models\TerminalMeetingSession;
use App\Support\BiometricCandidateSetBuilder;
use App\Support\MeetingOrganizationResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordFingerprintAttendance
{
    public function __construct(private BiometricCandidateSetBuilder $candidates, private MeetingOrganizationResolver $organizations) {}

    public function execute(TerminalMeetingSession $session, array $data): MeetingAttendanceRecord
    {
        return DB::transaction(function () use ($session, $data): MeetingAttendanceRecord {
            $sessionRow = TerminalMeetingSession::query()->findOrFail($session->id);
            $lockedMeeting = \App\Models\Meeting::query()->with(['units.unit'])->lockForUpdate()->findOrFail($sessionRow->meeting_id);
            $lockedSession = TerminalMeetingSession::query()->lockForUpdate()->findOrFail($sessionRow->id)->load(['terminal', 'participantVersion.members'])->setRelation('meeting', $lockedMeeting);
            if (in_array($lockedMeeting->status, ['POSTPONED', 'CANCELLED'], true)) {
                $this->fail('MEETING_NOT_EXECUTABLE', 422, 'حضور برای جلسهٔ متوقف یا لغوشده مجاز نیست.');
            }
            if (in_array($lockedMeeting->status, ['HELD', 'COMPLETED'], true)) {
                $this->fail('MEETING_ATTENDANCE_CLOSED', 422, 'حضور جلسه پس از پایان بسته شده است.');
            }
            $this->candidates->assertValid($lockedSession);

            $clientEvent = (string) $data['client_event_uuid'];
            $employeeId = (int) $data['employee_id'];
            $existingEvent = MeetingAttendanceRecord::query()->where('client_event_uuid', $clientEvent)->lockForUpdate()->first();
            if ($existingEvent !== null) {
                $sameContext = (int) $existingEvent->meeting_id === (int) $lockedSession->meeting_id
                    && (int) $existingEvent->terminal_session_id === (int) $lockedSession->id
                    && (int) $existingEvent->employee_id === $employeeId
                    && (string) ($existingEvent->matchedTemplate?->uuid ?? '') === (string) $data['matched_template_uuid'];

                if (!$sameContext) {
                    $this->fail('ATTENDANCE_EVENT_UUID_CONFLICT', 409, 'شناسهٔ رویداد حضور قبلاً برای رویداد دیگری استفاده شده است.');
                }

                return $existingEvent->load(['employee', 'matchedTemplate']);
            }

            $member = $lockedSession->participantVersion->members->firstWhere('employee_id', $employeeId);
            if ($member === null) $this->fail('BIOMETRIC_MATCH_EMPLOYEE_NOT_PARTICIPANT', 422, 'کارمند عضو نسخهٔ رسمی اشتراک‌کنندگان نیست.');

            $template = EmployeeBiometricTemplate::query()->where('uuid', $data['matched_template_uuid'])->lockForUpdate()->first();
            if ($template === null || (int) $template->employee_id !== $employeeId) $this->fail('BIOMETRIC_TEMPLATE_STALE', 422, 'قالب بایومتریک با کارمند هم‌خوان نیست.');
            if ($template->status === EmployeeBiometricTemplate::REVOKED) $this->fail('BIOMETRIC_TEMPLATE_REVOKED', 422, 'قالب بایومتریک لغو شده است.');
            if ($template->status !== EmployeeBiometricTemplate::ACTIVE) $this->fail('BIOMETRIC_TEMPLATE_STALE', 422, 'قالب بایومتریک منسوخ شده است.');
            if ((int) $template->organization_id !== (int) $this->organizations->id($lockedSession->meeting)) $this->fail('BIOMETRIC_TEMPLATE_STALE', 422, 'قالب بایومتریک در محدودهٔ جلسه نیست.');

            $package = $this->candidates->build($lockedSession);
            if (!hash_equals($package['candidate_set_checksum'], (string) $data['candidate_set_checksum'])) {
                $this->fail('BIOMETRIC_CANDIDATE_SET_STALE', 422, 'مجموعهٔ بایومتریک جلسه تازه نیست.');
            }
            if (!collect($package['candidates'])->contains(fn (array $candidate): bool => $candidate['template_uuid'] === $template->uuid)) $this->fail('BIOMETRIC_TEMPLATE_STALE', 422, 'قالب در مجموعهٔ فعلی جلسه نیست.');

            $already = MeetingAttendanceRecord::query()->where('meeting_id', $lockedSession->meeting_id)->where('employee_id', $employeeId)->lockForUpdate()->first();
            if ($already !== null) $this->fail('ATTENDANCE_ALREADY_RECORDED', 409, 'حضور قبلاً ثبت شده است.');

            return MeetingAttendanceRecord::create([
                'uuid' => (string) Str::uuid(),
                'meeting_id' => $lockedSession->meeting_id,
                'employee_id' => $employeeId,
                'participant_version_id' => $lockedSession->participant_version_id,
                'terminal_id' => $lockedSession->terminal_id,
                'terminal_session_id' => $lockedSession->id,
                'attendance_method' => 'FINGERPRINT',
                'finger_position' => $data['finger_position'] ?? $template->finger_position,
                'matched_template_id' => $template->id,
                'matcher_vendor' => $data['matcher_vendor'] ?? null,
                'matcher_algorithm_version' => $data['matcher_algorithm_version'] ?? null,
                'match_score' => $data['match_score'] ?? null,
                'threshold_reference' => $data['threshold_reference'] ?? null,
                'candidate_set_checksum' => $package['candidate_set_checksum'],
                'client_event_uuid' => $clientEvent,
                'recorded_at_server' => now(),
                'device_captured_at' => $data['device_captured_at'] ?? null,
            ]);
        });
    }

    private function fail(string $code, int $status, string $message): never
    {
        abort(response()->json(['success' => false, 'code' => $code, 'message' => $message, 'errors' => []], $status));
    }
}
