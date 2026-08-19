<?php

namespace App\Support;

use App\Models\EmployeeBiometricTemplate;
use App\Models\TerminalMeetingSession;
use App\Support\TerminalAuditLogger;
use Illuminate\Support\Facades\DB;

class BiometricCandidateSetBuilder
{
    public function __construct(private TerminalAuditLogger $audit) {}

    public function build(TerminalMeetingSession $session): array
    {
        $session->loadMissing(['terminal', 'meeting', 'participantVersion.members.employee']);
        $this->assertValid($session);

        $members = $session->participantVersion->members;
        $employeeIds = $members->pluck('employee_id')->map(fn ($id) => (int) $id)->unique()->values();
        $templates = EmployeeBiometricTemplate::query()
            ->active()
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('employee_id')
            ->orderBy('finger_position')
            ->orderBy('version_number')
            ->get();
        $candidateIdentities = $templates->map(fn (EmployeeBiometricTemplate $template): array => [
            'employee_id' => (int) $template->employee_id,
            'template_uuid' => $template->uuid,
            'finger_position' => $template->finger_position,
            'version_number' => (int) $template->version_number,
            'template_checksum' => $template->template_checksum,
        ])->values()->all();
        $checksumInput = ['participant_version_id' => (int) $session->participant_version_id, 'candidates' => $candidateIdentities];
        $checksum = hash('sha256', json_encode($checksumInput, JSON_UNESCAPED_SLASHES));
        $readyEmployeeIds = $templates->pluck('employee_id')->unique()->values();

        $payload = [
            'session_uuid' => $session->uuid,
            'meeting_id' => (int) $session->meeting_id,
            'participant_version_id' => (int) $session->participant_version_id,
            'candidate_set_checksum' => $checksum,
            'total_participants' => $employeeIds->count(),
            'biometric_ready' => $readyEmployeeIds->count(),
            'without_biometrics' => max(0, $employeeIds->count() - $readyEmployeeIds->count()),
            'candidates' => $templates->map(function (EmployeeBiometricTemplate $template): array {
                $item = [
                    'employee_id' => (int) $template->employee_id,
                    'template_uuid' => $template->uuid,
                    'finger_position' => $template->finger_position,
                    'template_vendor' => $template->template_vendor,
                    'template_format' => $template->template_format,
                    'algorithm_version' => $template->algorithm_version,
                    'template_data' => $template->template_data,
                    'template_checksum' => $template->template_checksum,
                ];
                if (config('biometric.device_driver') === 'fake') $item['fake_probe_fixture'] = 'EMPLOYEE_' . $template->employee_id;
                return $item;
            })->values()->all(),
        ];

        $this->audit->record('BIOMETRIC_CANDIDATE_SET_ACCESSED', null, $session->terminal, $session->meeting, $session->assignment, $session, [
            'participant_version_id' => $session->participant_version_id,
            'candidate_set_checksum' => $checksum,
            'candidate_count' => count($payload['candidates']),
            'biometric_ready' => $payload['biometric_ready'],
        ]);
        return $payload;
    }

    public function assertValid(TerminalMeetingSession $session): void
    {
        if (!$session->isEffectivelyActive() || $session->terminal?->status !== 'ACTIVE') {
            abort(response()->json(['success' => false, 'code' => 'ATTENDANCE_SESSION_NOT_ACTIVE', 'message' => 'نشست ترمینال فعال نیست.', 'errors' => []], 422));
        }
        if ((int) $session->meeting->current_schedule_version_id !== (int) $session->schedule_version_id || (int) $session->meeting->current_participant_version_id !== (int) $session->participant_version_id) {
            abort(response()->json(['success' => false, 'code' => 'TERMINAL_SESSION_STALE', 'message' => 'نسخهٔ جلسه تغییر کرده است.', 'errors' => ['schedule_changed' => (int) $session->meeting->current_schedule_version_id !== (int) $session->schedule_version_id, 'participants_changed' => (int) $session->meeting->current_participant_version_id !== (int) $session->participant_version_id]], 422));
        }
    }
}
