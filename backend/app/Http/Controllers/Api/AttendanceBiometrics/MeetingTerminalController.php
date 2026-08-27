<?php

namespace App\Http\Controllers\Api\AttendanceBiometrics;

use App\Actions\Meetings\AssignTerminalToMeeting;
use App\Actions\Meetings\RefreshMeetingTerminalAssignment;
use App\Actions\Meetings\RevokeMeetingTerminalAssignment;
use App\Http\Controllers\Controller;
use App\Http\Requests\AttendanceBiometrics\StoreMeetingTerminalAssignmentRequest;
use App\Http\Requests\AttendanceBiometrics\TerminalRevokeRequest;
use App\Http\Resources\MeetingResource;
use App\Http\Resources\MeetingTerminalAssignmentResource;
use App\Http\Resources\TerminalResource;
use App\Models\Meeting;
use App\Models\MeetingTerminalAssignment;
use App\Support\MeetingOrganizationResolver;
use App\Models\Terminal;
use App\Support\TerminalAssignmentGovernanceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingTerminalController extends Controller
{
    public function workspace(Meeting $meeting, TerminalAssignmentGovernanceResolver $governance, MeetingOrganizationResolver $organizations): JsonResponse
    {
        $this->authorize('terminalAssignmentView', $meeting);
        $meeting->load([
            'request', 'units.unit', 'authorityContext', 'currentScheduleVersion', 'currentParticipantVersion',
            'terminalAssignments.terminal', 'terminalAssignments.meeting', 'terminalAssignments.sessions',
            'currentTerminalSession',
        ]);
        $organizationId = $organizations->id($meeting);
        $terminals = Terminal::query()->where('organization_id', $organizationId)->where('status', 'ACTIVE')->orderBy('code')->get();
        return response()->json([
            'success' => true,
            'data' => [
                'meeting' => new MeetingResource($meeting),
                'governance' => $governance->resolve($meeting),
                'assignments' => MeetingTerminalAssignmentResource::collection($meeting->terminalAssignments),
                'available_terminals' => TerminalResource::collection($terminals),
            ],
        ]);
    }

    public function assign(StoreMeetingTerminalAssignmentRequest $request, Meeting $meeting, AssignTerminalToMeeting $action): JsonResponse
    {
        $this->authorize('terminalAssignmentAssign', $meeting);
        $terminal = Terminal::query()->findOrFail($request->validated()['terminal_id']);
        $assignment = $action->execute($request->user(), $meeting, $terminal, $request->validated()['valid_from'], $request->validated()['valid_until']);
        return (new MeetingTerminalAssignmentResource($assignment))->additional(['success' => true, 'message' => 'ترمینال به جلسه تخصیص داده شد.'])->response()->setStatusCode(201);
    }

    public function refresh(Request $request, Meeting $meeting, MeetingTerminalAssignment $assignment, RefreshMeetingTerminalAssignment $action): MeetingTerminalAssignmentResource
    {
        $this->authorize('terminalAssignmentRefresh', [$meeting, $assignment]);
        return new MeetingTerminalAssignmentResource($action->execute($request->user(), $meeting, $assignment));
    }

    public function revoke(TerminalRevokeRequest $request, Meeting $meeting, MeetingTerminalAssignment $assignment, RevokeMeetingTerminalAssignment $action): MeetingTerminalAssignmentResource
    {
        $this->authorize('terminalAssignmentRevoke', [$meeting, $assignment]);
        return new MeetingTerminalAssignmentResource($action->execute($request->user(), $meeting, $assignment, $request->validated()['reason']));
    }
}
