<?php

namespace App\Http\Controllers\Api;

use App\Actions\Terminals\ActivateTerminalMeetingSession;
use App\Actions\Terminals\CloseTerminalMeetingSession;
use App\Actions\Biometrics\BuildMeetingBiometricCandidateSet;
use App\Actions\Attendance\RecordFingerprintAttendance;
use App\Http\Requests\AttendanceBiometrics\RecordFingerprintAttendanceRequest;
use App\Http\Resources\MeetingAttendanceResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceBiometrics\TerminalRevokeRequest;
use App\Http\Resources\TerminalMeetingSessionResource;
use App\Support\TerminalManifestBuilder;
use App\Support\BiometricCandidateSetBuilder;
use App\Models\MeetingTerminalAssignment;
use App\Models\TerminalMeetingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TerminalApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $terminal = $request->attributes->get('terminal');
        return response()->json(['success' => true, 'data' => [
            'terminal_uuid' => $terminal->uuid,
            'code' => $terminal->code,
            'name' => $terminal->name,
            'manufacturer' => $terminal->manufacturer,
            'model' => $terminal->model,
            'platform' => $terminal->platform,
            'os_version' => $terminal->os_version,
            'app_version' => $terminal->app_version,
            'status' => $terminal->status,
        ]]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $terminal = $request->attributes->get('terminal');
        $validated = $request->validate(['app_version' => ['sometimes', 'string', 'max:80']]);
        $terminal->forceFill(array_filter([
            'app_version' => $validated['app_version'] ?? null,
            'last_seen_at' => now(),
        ], fn ($value): bool => $value !== null))->saveQuietly();
        return response()->json(['success' => true, 'data' => ['terminal_uuid' => $terminal->uuid, 'last_seen_at' => $terminal->fresh()->last_seen_at]]);
    }

    public function currentAssignments(Request $request): JsonResponse
    {
        $terminal = $request->attributes->get('terminal');
        $assignments = MeetingTerminalAssignment::query()
            ->with(['meeting.currentScheduleVersion', 'meeting.currentParticipantVersion'])
            ->where('terminal_id', $terminal->id)
            ->whereIn('status', ['ASSIGNED', 'STALE'])
            ->where('valid_until', '>=', now())
            ->orderBy('valid_from')
            ->get()
            ->map(function (MeetingTerminalAssignment $assignment): array {
                $stale = $assignment->staleness($assignment->meeting);
                return [
                    'assignment_uuid' => $assignment->uuid,
                    'meeting_id' => $assignment->meeting_id,
                    'meeting_number' => $assignment->meeting->meeting_number,
                    'subject' => $assignment->meeting->title,
                    'valid_from' => $assignment->valid_from,
                    'valid_until' => $assignment->valid_until,
                    'status' => $assignment->status,
                    'is_stale' => $stale['schedule_changed'] || $stale['participants_changed'],
                    'schedule_changed' => $stale['schedule_changed'],
                    'participants_changed' => $stale['participants_changed'],
                    'baseline_schedule_version_id' => $assignment->baseline_schedule_version_id,
                    'baseline_participant_version_id' => $assignment->baseline_participant_version_id,
                ];
            });
        return response()->json(['success' => true, 'data' => $assignments]);
    }

    public function activate(Request $request, MeetingTerminalAssignment $assignment, ActivateTerminalMeetingSession $action): JsonResponse
    {
        $terminal = $request->attributes->get('terminal');
        $result = $action->execute($terminal, $assignment);
        return response()->json([
            'success' => true,
            'message' => 'نشست ترمینال فعال شد. کلید نشست فقط یک‌بار نمایش داده می‌شود.',
            'data' => [
                'session' => new TerminalMeetingSessionResource($result['session']),
                'session_token' => $result['session_token'],
            ],
        ], 201);
    }

    public function manifest(Request $request, TerminalManifestBuilder $builder): JsonResponse
    {
        /** @var TerminalMeetingSession $session */
        $session = $request->attributes->get('terminalSession');
        $this->assertFresh($session);
        return response()->json(['success' => true, 'data' => $builder->build($session)]);
    }

    public function biometricCandidates(Request $request, BuildMeetingBiometricCandidateSet $action): JsonResponse
    {
        /** @var TerminalMeetingSession $session */
        $session = $request->attributes->get('terminalSession');
        $this->assertFresh($session);
        return response()->json(['success' => true, 'data' => $action->execute($session)]);
    }

    public function fingerprintAttendance(RecordFingerprintAttendanceRequest $request, RecordFingerprintAttendance $action): MeetingAttendanceResource
    {
        /** @var TerminalMeetingSession $session */
        $session = $request->attributes->get('terminalSession');
        $record = $action->execute($session, $request->validated());
        return new MeetingAttendanceResource($record->load(['employee', 'matchedTemplate']));
    }

    public function close(Request $request, CloseTerminalMeetingSession $action): TerminalMeetingSessionResource
    {
        /** @var TerminalMeetingSession $session */
        $session = $request->attributes->get('terminalSession');
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        return new TerminalMeetingSessionResource($action->execute($session, $validated['reason']));
    }

    private function assertFresh(TerminalMeetingSession $session): void
    {
        $meeting = $session->meeting()->firstOrFail();
        $stale = [
            'schedule_changed' => (int) $session->schedule_version_id !== (int) $meeting->current_schedule_version_id,
            'participants_changed' => (int) $session->participant_version_id !== (int) $meeting->current_participant_version_id,
        ];
        if ($stale['schedule_changed'] || $stale['participants_changed']) {
            $session->forceFill(['status' => 'REVOKED', 'closed_at' => now(), 'close_reason' => 'BOUND_VERSION_CHANGED'])->saveQuietly();
            abort(response()->json(['success' => false, 'code' => 'TERMINAL_SESSION_STALE', 'message' => 'نسخهٔ جلسه پس از فعال‌سازی تغییر کرده است.', 'errors' => $stale], 422));
        }
    }
}
