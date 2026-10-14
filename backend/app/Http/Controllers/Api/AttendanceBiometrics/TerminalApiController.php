<?php

namespace App\Http\Controllers\Api\AttendanceBiometrics;

use App\Actions\Terminals\ActivateTerminalMeetingSession;
use App\Actions\Terminals\CloseTerminalMeetingSession;
use App\Actions\Biometrics\BuildMeetingBiometricCandidateSet;
use App\Actions\Attendance\RecordFingerprintAttendance;
use App\Http\Requests\AttendanceBiometrics\RecordFingerprintAttendanceRequest;
use App\Http\Resources\MeetingAttendanceResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceBiometrics\TerminalRevokeRequest;
use App\Http\Resources\TerminalMeetingParticipantResource;
use App\Http\Resources\TerminalMeetingSessionResource;
use App\Support\TerminalManifestBuilder;
use App\Support\BiometricCandidateSetBuilder;
use App\Models\MeetingTerminalAssignment;
use App\Models\MeetingAttendanceRecord;
use App\Models\MeetingParticipantVersion;
use App\Models\MeetingParticipantVersionMember;
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
                    'meeting_status' => $assignment->meeting->status,
                    'meeting_date' => $assignment->meeting->currentScheduleVersion?->meeting_date?->toDateString(),
                    'start_time' => $assignment->meeting->currentScheduleVersion?->start_time,
                    'end_time' => $assignment->meeting->currentScheduleVersion?->end_time,
                    'location' => $assignment->meeting->currentScheduleVersion?->location,
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

    public function assignmentParticipants(Request $request, MeetingTerminalAssignment $assignment)
    {
        $terminal = $request->attributes->get('terminal');
        $assignment = MeetingTerminalAssignment::query()
            ->with(['meeting.currentScheduleVersion', 'meeting.currentParticipantVersion'])
            ->whereKey($assignment->id)
            ->where('terminal_id', $terminal->id)
            ->firstOrFail();

        if ($assignment->status === 'REVOKED') {
            abort(404, 'TERMINAL_ASSIGNMENT_NOT_FOUND');
        }

        if ($assignment->status === 'EXPIRED' || now()->greaterThan($assignment->valid_until)) {
            abort(response()->json([
                'success' => false,
                'code' => 'TERMINAL_ASSIGNMENT_EXPIRED',
                'message' => 'بازهٔ مجاز مشاهدهٔ فهرست این جلسه پایان یافته است.',
                'errors' => new \stdClass(),
            ], 422));
        }

        if (now()->lessThan($assignment->valid_from)) {
            abort(response()->json([
                'success' => false,
                'code' => 'TERMINAL_ASSIGNMENT_WINDOW_NOT_OPEN',
                'message' => 'فهرست اشتراک‌کنندگان پس از آغاز پنجرهٔ مجاز نمایش داده می‌شود.',
                'errors' => new \stdClass(),
            ], 403));
        }

        if ($assignment->status !== 'ASSIGNED' || $assignment->meeting->status !== 'SCHEDULED') {
            abort(response()->json([
                'success' => false,
                'code' => 'TERMINAL_ASSIGNMENT_UNAVAILABLE',
                'message' => 'این تخصیص ترمینال برای نمایش فهرست اشتراک‌کنندگان معتبر نیست.',
                'errors' => new \stdClass(),
            ], 422));
        }

        $stale = $assignment->staleness($assignment->meeting);
        if ($stale['schedule_changed'] || $stale['participants_changed']) {
            abort(response()->json([
                'success' => false,
                'code' => 'TERMINAL_ASSIGNMENT_STALE',
                'message' => 'تخصیص ترمینال با آخرین نسخهٔ جلسه یا اشتراک‌کنندگان هماهنگ نیست.',
                'errors' => $stale,
            ], 422));
        }

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $participantVersion = MeetingParticipantVersion::query()
            ->whereKey($assignment->baseline_participant_version_id)
            ->where('meeting_id', $assignment->meeting_id)
            ->first();

        if (!$participantVersion) {
            abort(response()->json([
                'success' => false,
                'code' => 'TERMINAL_ASSIGNMENT_STALE',
                'message' => 'نسخهٔ اشتراک‌کنندگان این تخصیص دیگر معتبر نیست.',
                'errors' => ['participants_changed' => true],
            ], 422));
        }

        $query = MeetingParticipantVersionMember::query()
            ->where('participant_version_id', $participantVersion->id)
            ->with(['employee.position'])
            ->addSelect(['attendance_exists' => MeetingAttendanceRecord::query()
                ->selectRaw('1')
                ->where('meeting_id', $assignment->meeting_id)
                ->whereColumn('meeting_attendance_records.employee_id', 'meeting_participant_version_members.employee_id')
                ->limit(1)])
            ->orderBy('id');

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->whereHas('employee', function ($employeeQuery) use ($search): void {
                $employeeQuery->where(function ($fields) use ($search): void {
                    $fields->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('employee_number', 'like', '%'.$search.'%');
                });
            });
        }

        $paginator = $query->paginate($validated['per_page'] ?? 50);

        return TerminalMeetingParticipantResource::collection($paginator)->additional([
            'success' => true,
            'assignment_uuid' => $assignment->uuid,
            'participant_version_id' => $participantVersion->id,
        ]);
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
        ], ($result['resumed'] ?? false) ? 200 : 201);
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
