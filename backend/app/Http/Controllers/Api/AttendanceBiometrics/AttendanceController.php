<?php

namespace App\Http\Controllers\Api\AttendanceBiometrics;

use App\Actions\Attendance\RecordManualAttendance;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceBiometrics\RecordManualAttendanceRequest;
use App\Http\Requests\PaginatedRequest;
use App\Http\Resources\MeetingAttendanceResource;
use App\Models\Meeting;
use App\Support\AttendanceGovernanceResolver;
use App\Support\OfficialAttendanceResolver;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
    public function index(PaginatedRequest $request, Meeting $meeting, OfficialAttendanceResolver $officialAttendance): JsonResponse
    {
        $this->authorize('view', $meeting);

        $recordsQuery = $officialAttendance->query($meeting);
        $totalCount = (clone $recordsQuery)->count();
        $biometricCount = (clone $recordsQuery)->where('attendance_method', 'FINGERPRINT')->count();
        $manualCount = (clone $recordsQuery)->where('attendance_method', '!=', 'FINGERPRINT')->count();
        $records = (clone $recordsQuery)
            ->with(['employee', 'terminal'])
            ->orderByDesc('recorded_at_server')
            ->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 25), 1), 50));
        $recordsPayload = MeetingAttendanceResource::collection($records)->response()->getData(true);

        return response()->json(['success' => true, 'data' => [
            'meeting_id' => $meeting->id,
            'official_count' => $totalCount,
            'stats' => [
                'total' => $totalCount,
                'biometric' => $biometricCount,
                'manual' => $manualCount,
            ],
            'records' => $recordsPayload['data'],
            'records_meta' => $recordsPayload['meta'],
            'records_links' => $recordsPayload['links'],
        ]]);
    }

    public function manual(RecordManualAttendanceRequest $request, Meeting $meeting, RecordManualAttendance $action, AttendanceGovernanceResolver $governance): MeetingAttendanceResource
    {
        $this->authorize('view', $meeting);
        if (! $governance->canOverride($request->user(), $meeting)) {
            abort(response()->json(['success' => false, 'code' => 'UNAUTHORIZED_ATTENDANCE_OVERRIDE', 'message' => 'ثبت دستی حضور برای شما مجاز نیست.', 'errors' => []], 403));
        }

        return new MeetingAttendanceResource($action->execute($request->user(), $meeting, $request->integer('employee_id'), $request->string('reason')->toString()));
    }
}
