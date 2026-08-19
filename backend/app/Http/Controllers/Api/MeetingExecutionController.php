<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Meetings\EndMeetingExecution;
use App\Actions\Meetings\StartMeetingExecution;
use App\Http\Controllers\Controller;
use App\Http\Requests\EndMeetingExecutionRequest;
use App\Http\Requests\StartMeetingExecutionRequest;
use App\Http\Resources\MeetingExecutionSessionResource;
use App\Models\Meeting;
use Illuminate\Http\JsonResponse;

class MeetingExecutionController extends Controller
{
    public function show(Meeting $meeting): JsonResponse
    {
        $this->authorize('executionView', $meeting);
        $meeting->load(['request', 'currentScheduleVersion', 'currentParticipantVersion', 'executionSession']);
        return response()->json([
            'success' => true,
            'data' => [
                'meeting_id' => $meeting->id,
                'meeting_number' => $meeting->meeting_number,
                'status' => $meeting->status,
                'schedule_version_id' => $meeting->current_schedule_version_id,
                'participant_version_id' => $meeting->current_participant_version_id,
                'execution_session' => $meeting->executionSession ? new MeetingExecutionSessionResource($meeting->executionSession) : null,
            ],
        ]);
    }

    public function start(StartMeetingExecutionRequest $request, Meeting $meeting, StartMeetingExecution $action): MeetingExecutionSessionResource
    {
        $this->authorize('executionStart', $meeting);
        return new MeetingExecutionSessionResource($action->execute($request->user(), $meeting, $request->validated()['start_notes'] ?? null));
    }

    public function end(EndMeetingExecutionRequest $request, Meeting $meeting, EndMeetingExecution $action): MeetingExecutionSessionResource
    {
        $this->authorize('executionEnd', $meeting);
        return new MeetingExecutionSessionResource($action->execute($request->user(), $meeting, $request->validated()['end_notes'] ?? null));
    }
}
