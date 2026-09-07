<?php

namespace App\Http\Controllers\Api\Meetings;

use App\Actions\Meetings\AddMeetingParticipantChangeItem;
use App\Actions\Meetings\ConfirmMeetingParticipantChange;
use App\Actions\Meetings\CreateMeetingParticipantChangeCase;
use App\Actions\Meetings\DeleteMeetingParticipantChangeItem;
use App\Actions\Meetings\RefreshParticipantChangeCaseBaseline;
use App\Actions\Meetings\RejectMeetingParticipantChange;
use App\Actions\Meetings\RemoveMeetingParticipantChangeItem;
use App\Actions\Meetings\ReturnMeetingParticipantChange;
use App\Actions\Meetings\SubmitMeetingParticipantChange;
use App\Actions\Meetings\UpdateMeetingParticipantChangeCase;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\MeetingResource;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\MeetingParticipantChangeItem;
use App\Support\MeetingWorkspacePagination;
use App\Support\ParticipantCandidateQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MeetingParticipantChangeController
{
    public function employees(Request $request, Meeting $meeting)
    {
        Gate::authorize('participantChangeViewHistory', $meeting);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer'], 'search' => ['sometimes', 'nullable', 'string', 'max:200']]);
        $query = app(ParticipantCandidateQuery::class)->forActor($meeting, $request->user());
        if ($request->filled('search')) {
            $term = '%'.trim($request->string('search')->toString()).'%';
            $query->where(fn ($q) => $q->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('employee_number', 'like', $term));
        }

        return EmployeeResource::collection($query->orderBy('first_name')->orderBy('id')
            ->paginate(min(max($request->integer('per_page', 15), 1), 50)));
    }

    public function workspace(Request $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('participantChangeViewHistory', $meeting);

        return response()->json(['data' => $this->workspaceData($meeting, $request)]);
    }

    public function createCase(Request $request, Meeting $meeting, CreateMeetingParticipantChangeCase $action): JsonResponse
    {
        Gate::authorize('participantChangeCreateCase', $meeting);
        $reason = $request->validate(['reason' => ['required', 'string']])['reason'];

        return response()->json(['data' => $action->execute($meeting, $request->user(), $reason)], 201);
    }

    public function updateCase(Request $request, Meeting $meeting, MeetingParticipantChangeCase $case, UpdateMeetingParticipantChangeCase $action): JsonResponse
    {
        $this->assertCaseBelongsToMeeting($case, $meeting);
        $data = $request->validate(['reason' => ['required', 'string']]);

        return response()->json(['data' => $action->execute($meeting, $case, $request->user(), $data['reason'])]);
    }

    public function addItem(Request $request, Meeting $meeting, MeetingParticipantChangeCase $case, AddMeetingParticipantChangeItem $action): JsonResponse
    {
        $this->assertCaseBelongsToMeeting($case, $meeting);
        $data = $request->validate([
            'operation' => ['required', 'in:ADD,REPLACE'],
            'old_employee_id' => ['nullable', 'integer'],
            'new_employee_id' => ['nullable', 'integer'],
            'participant_role' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['data' => $action->execute($meeting, $case, $request->user(), $data['operation'], $data['old_employee_id'] ?? null, $data['new_employee_id'] ?? null, $data['participant_role'] ?? null)], 201);
    }

    public function removeItem(Request $request, Meeting $meeting, MeetingParticipantChangeCase $case, RemoveMeetingParticipantChangeItem $action): JsonResponse
    {
        $this->assertCaseBelongsToMeeting($case, $meeting);
        $employeeId = $request->validate(['employee_id' => ['required', 'integer']])['employee_id'];

        return response()->json(['data' => $action->execute($meeting, $case, $request->user(), $employeeId)], 201);
    }

    public function deleteItem(Request $request, Meeting $meeting, MeetingParticipantChangeCase $case, MeetingParticipantChangeItem $item, DeleteMeetingParticipantChangeItem $action): JsonResponse
    {
        $this->assertCaseBelongsToMeeting($case, $meeting);
        $action->execute($meeting, $case, $item, $request->user());

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function submit(Request $request, Meeting $meeting, MeetingParticipantChangeCase $case, SubmitMeetingParticipantChange $action): JsonResponse
    {
        $this->assertCaseBelongsToMeeting($case, $meeting);

        return response()->json(['data' => $action->execute($meeting, $case, $request->user())]);
    }

    public function returnForCorrection(Request $request, Meeting $meeting, MeetingParticipantChangeCase $case, ReturnMeetingParticipantChange $action): JsonResponse
    {
        $this->assertCaseBelongsToMeeting($case, $meeting);
        $reason = $request->validate(['reason' => ['required', 'string']])['reason'];

        return response()->json(['data' => $action->execute($meeting, $case, $request->user(), $reason)]);
    }

    public function reject(Request $request, Meeting $meeting, MeetingParticipantChangeCase $case, RejectMeetingParticipantChange $action): JsonResponse
    {
        $this->assertCaseBelongsToMeeting($case, $meeting);

        return response()->json(['data' => $action->execute($meeting, $case, $request->user(), $request->input('reason'))]);
    }

    public function refreshBaseline(Request $request, Meeting $meeting, MeetingParticipantChangeCase $case, RefreshParticipantChangeCaseBaseline $action): JsonResponse
    {
        $this->assertCaseBelongsToMeeting($case, $meeting);

        return response()->json(['data' => $action->execute($meeting, $case, $request->user())]);
    }

    public function confirm(Request $request, Meeting $meeting, MeetingParticipantChangeCase $case, ConfirmMeetingParticipantChange $action): JsonResponse
    {
        $this->assertCaseBelongsToMeeting($case, $meeting);

        return response()->json(['data' => $action->execute($meeting, $case, $request->user())]);
    }

    public function history(Request $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('participantChangeViewHistory', $meeting);
        $versions = MeetingWorkspacePagination::read($meeting->participantVersions()->with(['members.employee.unit', 'sourceChangeCase'])->orderByDesc('version_number')->orderByDesc('id'), $request);
        $actions = MeetingWorkspacePagination::read($this->participantActions($meeting), $request);

        return response()->json(['data' => [
            'meeting_id' => $meeting->id,
            'current_participant_version' => $meeting->currentParticipantVersion,
            'participant_versions' => $versions['data'], 'participant_versions_meta' => $versions['meta'],
            'actions' => $actions['data'], 'actions_meta' => $actions['meta'],
        ]]);
    }

    private function workspaceData(Meeting $meeting, Request $request): array
    {
        $meeting->load([
            'request', 'units.unit', 'participants.employee.unit', 'chairperson', 'authorityContext.authorityEmployee',
            'currentScheduleVersion', 'currentParticipantVersion.members.employee.unit',
            'activeParticipantChangeCase.items.oldEmployee.unit', 'activeParticipantChangeCase.items.newEmployee.unit',
            'activeParticipantChangeCase.ownerUnit', 'activeParticipantChangeCase.baselineScheduleVersion',
        ]);
        $versions = MeetingWorkspacePagination::read($meeting->participantVersions()->orderByDesc('version_number')->orderByDesc('id'), $request);
        $actions = MeetingWorkspacePagination::read($this->participantActions($meeting), $request);

        return [
            'meeting' => new MeetingResource($meeting),
            'current_schedule_version' => $meeting->currentScheduleVersion,
            'current_participant_version' => $meeting->currentParticipantVersion,
            'current_participants' => $meeting->participants,
            'active_participant_change_case' => $meeting->activeParticipantChangeCase,
            'participant_versions' => $versions['data'], 'participant_versions_meta' => $versions['meta'],
            'participant_actions' => $actions['data'], 'participant_actions_meta' => $actions['meta'],
            'employee_options' => $this->employeeOptions($meeting, $request->user()),
        ];
    }

    private function participantActions(Meeting $meeting)
    {
        return $meeting->rescheduleActions()->with('actor')->where(fn ($query) => $query
            ->where('action', 'like', 'PARTICIPANT_CHANGE%')->orWhere('action', 'MEETING_PARTICIPANTS_CHANGED'))->latest('id');
    }

    private function employeeOptions(Meeting $meeting, $actor)
    {
        return app(ParticipantCandidateQuery::class)->forActor($meeting, $actor)
            ->orderBy('first_name')->orderBy('id')->limit(15)->get();
    }

    private function assertCaseBelongsToMeeting(MeetingParticipantChangeCase $case, Meeting $meeting): void
    {
        abort_unless((int) $case->meeting_id === (int) $meeting->id, 404, 'PARTICIPANT_CHANGE_CASE_NOT_FOUND');
    }
}
