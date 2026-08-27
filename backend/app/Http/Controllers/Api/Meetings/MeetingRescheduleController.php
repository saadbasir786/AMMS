<?php

namespace App\Http\Controllers\Api\Meetings;

use App\Actions\Meetings\AcknowledgeMeetingRescheduleProposal;
use App\Actions\Meetings\ConfirmMeetingReschedule;
use App\Actions\Meetings\CreateAuthorityRescheduleProposal;
use App\Actions\Meetings\CreateMeetingRescheduleCase;
use App\Actions\Meetings\CreatePeerRescheduleProposal;
use App\Actions\Meetings\RequestMeetingScheduleAlternative;
use App\Actions\Meetings\ResolveMeetingScheduleAlternative;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Models\MeetingRescheduleAlternativeRequest;
use App\Models\MeetingRescheduleProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeetingRescheduleController
{
    public function workspace(Request $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('meetingRescheduleViewHistory', $meeting);
        return response()->json(['data' => $this->workspaceData($meeting)]);
    }

    public function history(Request $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('meetingRescheduleViewHistory', $meeting);
        return response()->json(['data' => [
            'meeting_id' => $meeting->id,
            'current_schedule_version' => $meeting->currentScheduleVersion,
            'schedule_versions' => $meeting->scheduleVersions()->orderBy('version_number')->get(),
            'reschedule_cases' => $meeting->rescheduleCases()->with(['proposals.acknowledgements', 'alternativeRequests'])->latest()->get(),
            'actions' => $meeting->rescheduleActions()->with('actor')->oldest()->get(),
        ]]);
    }

    public function createCase(Request $request, Meeting $meeting, CreateMeetingRescheduleCase $action): JsonResponse
    {
        Gate::authorize('meetingRescheduleCreateCase', $meeting);
        $reason = $request->validate(['reason' => ['required', 'string']])['reason'];
        return response()->json(['data' => $action($request->user(), $meeting, $reason)], 201);
    }

    public function peerProposal(Request $request, Meeting $meeting, CreatePeerRescheduleProposal $action): JsonResponse
    {
        Gate::authorize('meetingReschedulePeerPropose', $meeting);
        $data = $this->proposalData($request);
        $case = $meeting->activeRescheduleCase()->firstOrFail();
        return response()->json(['data' => $action($request->user(), $meeting, $case, $data)], 201);
    }

    public function authorityProposal(Request $request, Meeting $meeting, CreateAuthorityRescheduleProposal $action): JsonResponse
    {
        Gate::authorize('meetingRescheduleAuthorityPropose', $meeting);
        $data = $this->proposalData($request);
        $case = $meeting->activeRescheduleCase()->firstOrFail();
        return response()->json(['data' => $action($request->user(), $meeting, $case, $data)], 201);
    }

    public function acknowledge(Request $request, Meeting $meeting, MeetingRescheduleProposal $proposal, AcknowledgeMeetingRescheduleProposal $action): JsonResponse
    {
        Gate::authorize('meetingRescheduleAcknowledge', $meeting);
        $case = $proposal->rescheduleCase()->firstOrFail();
        return response()->json(['data' => $action($request->user(), $meeting, $case, $proposal)]);
    }

    public function requestAlternative(Request $request, Meeting $meeting, MeetingRescheduleProposal $proposal, RequestMeetingScheduleAlternative $action): JsonResponse
    {
        Gate::authorize('meetingRescheduleRequestAlternative', $meeting);
        $data = $request->validate([
            'reason' => ['required', 'string'], 'suggested_date' => ['nullable', 'date'],
            'suggested_start_time' => ['nullable', 'date_format:H:i'], 'suggested_end_time' => ['nullable', 'date_format:H:i'],
        ]);
        $case = $proposal->rescheduleCase()->firstOrFail();
        return response()->json(['data' => $action($request->user(), $meeting, $case, $proposal, $data)], 201);
    }

    public function resolveAlternative(Request $request, Meeting $meeting, MeetingRescheduleAlternativeRequest $alternative, ResolveMeetingScheduleAlternative $action): JsonResponse
    {
        Gate::authorize('meetingRescheduleResolveAlternative', $meeting);
        return response()->json(['data' => $action($request->user(), $meeting, $alternative)]);
    }

    public function confirm(Request $request, Meeting $meeting, ConfirmMeetingReschedule $action): JsonResponse
    {
        Gate::authorize('meetingRescheduleConfirm', $meeting);
        $case = $meeting->activeRescheduleCase()->firstOrFail();
        return response()->json(['data' => new MeetingResource($action($request->user(), $meeting, $case))]);
    }

    private function proposalData(Request $request): array
    {
        return $request->validate([
            'meeting_date' => ['required', 'date'], 'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'], 'location' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string'],
        ]);
    }

    private function workspaceData(Meeting $meeting): array
    {
        $meeting->load([
            'request', 'units.unit', 'participants.employee.unit', 'authorityContext', 'chairperson',
            'currentScheduleVersion', 'scheduleVersions', 'activeRescheduleCase.proposals.acknowledgements.unit',
            'activeRescheduleCase.alternativeRequests.unit', 'rescheduleActions.actor',
        ]);
        return [
            'meeting' => new MeetingResource($meeting),
            'current_schedule_version' => $meeting->currentScheduleVersion,
            'active_reschedule_case' => $meeting->activeRescheduleCase,
            'schedule_versions' => $meeting->scheduleVersions->sortBy('version_number')->values(),
            'actions' => $meeting->rescheduleActions->sortBy('created_at')->values(),
        ];
    }
}
