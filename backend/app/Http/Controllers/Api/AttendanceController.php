<?php

namespace App\Http\Controllers\Api;

use App\Actions\Attendance\RecordManualAttendance;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceBiometrics\RecordManualAttendanceRequest;
use App\Http\Resources\MeetingAttendanceResource;
use App\Models\Meeting;
use App\Support\AttendanceGovernanceResolver;
use App\Support\OfficialAttendanceResolver;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
    public function index(Meeting $meeting, OfficialAttendanceResolver $officialAttendance): JsonResponse
    {
        $this->authorize('view', $meeting);
        return response()->json(['success' => true, 'data' => [
            'meeting_id' => $meeting->id,
            'official_count' => $officialAttendance->count($meeting),
            'records' => MeetingAttendanceResource::collection($officialAttendance->query($meeting)->with(['employee', 'terminal'])->latest('recorded_at_server')->get()),
        ]]);
    }

    public function manual(RecordManualAttendanceRequest $request, Meeting $meeting, RecordManualAttendance $action, AttendanceGovernanceResolver $governance): MeetingAttendanceResource
    {
        $this->authorize('view', $meeting);
        if (!$governance->canOverride($request->user(), $meeting)) {
            abort(response()->json(['success' => false, 'code' => 'UNAUTHORIZED_ATTENDANCE_OVERRIDE', 'message' => 'ثبت دستی حضور برای شما مجاز نیست.', 'errors' => []], 403));
        }
        return new MeetingAttendanceResource($action->execute($request->user(), $meeting, $request->integer('employee_id'), $request->string('reason')->toString()));
    }
}
