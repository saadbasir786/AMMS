<?php

namespace App\Http\Controllers\Api\Meetings;

use App\Actions\Meetings\AddMeetingParticipantChangeItem;
use App\Actions\Meetings\ApproveMeetingParticipantChange;
use App\Actions\Meetings\ConfirmMeetingParticipantChange;
use App\Actions\Meetings\CreateMeetingParticipantChangeCase;
use App\Actions\Meetings\DeleteMeetingParticipantChangeItem;
use App\Actions\Meetings\FinalizeMeetingParticipantChange;
use App\Actions\Meetings\RefreshParticipantChangeCaseBaseline;
use App\Actions\Meetings\RejectMeetingParticipantChange;
use App\Actions\Meetings\RemoveMeetingParticipantChangeItem;
use App\Actions\Meetings\ReturnMeetingParticipantChange;
use App\Actions\Meetings\SubmitMeetingParticipantChange;
use App\Actions\Meetings\UpdateMeetingParticipantChangeCase;
use App\Http\Resources\EmployeeResource;
use App\Http\Resources\MeetingResource;
use App\Models\Employee;
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
        $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'], 'search' => ['sometimes', 'nullable', 'string', 'max:200']]);
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
        $rules = (string) $meeting->request?->flow_type === 'INTERNAL_DIRECTORATE'
            ? ['reason' => ['required', 'string']]
            : ['reason' => ['sometimes', 'nullable', 'string']];
        $reason = $request->validate($rules)['reason'] ?? null;

        return response()->json(['data' => $action->execute($meeting, $case, $request->user(), $reason)]);
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

    public function approve(Request $request, Meeting $meeting, MeetingParticipantChangeCase $case, ApproveMeetingParticipantChange $action): JsonResponse
    {
        $this->assertCaseBelongsToMeeting($case, $meeting);
        Gate::authorize('participantChangeApprove', [$meeting, (string) $case->owner_type, $case->owner_unit_id]);

        return response()->json(['data' => $action->execute($meeting, $case, $request->user())]);
    }

    public function finalize(Request $request, Meeting $meeting, MeetingParticipantChangeCase $case, FinalizeMeetingParticipantChange $action): JsonResponse
    {
        $this->assertCaseBelongsToMeeting($case, $meeting);
        abort_if(
            (string) $meeting->request?->flow_type === 'INTERNAL_DIRECTORATE'
                && (int) $case->approved_by_user_id === (int) $request->user()->id,
            403,
            'PARTICIPANT_CHANGE_APPROVER_CANNOT_FINALIZE',
        );
        Gate::authorize('participantChangeFinalize', [$meeting, (string) $case->owner_type, $case->owner_unit_id]);

        return response()->json(['data' => $action->execute($meeting, $case, $request->user())]);
    }

    public function history(Request $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('participantChangeViewHistory', $meeting);
        $versionsPage = $request->has('participant_versions_page') ? 'participant_versions_page' : 'page';
        $actionsPage = $request->has('actions_page') ? 'actions_page' : 'page';
        $versions = MeetingWorkspacePagination::read($meeting->participantVersions()->with([
            'members.employee.unit',
            'sourceChangeCase.items.oldEmployee.unit',
            'sourceChangeCase.items.newEmployee.unit',
            'createdBy',
        ])->orderByDesc('version_number')->orderByDesc('id'), $request, $versionsPage);
        $actions = $this->withParticipantSnapshots(MeetingWorkspacePagination::read($this->participantActions($meeting), $request, $actionsPage));

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
            'activeParticipantChangeCase.submittedBy', 'activeParticipantChangeCase.approvedBy',
        ]);
        $versionsPage = $request->has('participant_versions_page') ? 'participant_versions_page' : 'page';
        $actionsPage = $request->has('actions_page') ? 'actions_page' : 'page';
        $versions = MeetingWorkspacePagination::read($meeting->participantVersions()->with([
            'members.employee.unit',
            'sourceChangeCase.items.oldEmployee.unit',
            'sourceChangeCase.items.newEmployee.unit',
            'createdBy',
        ])->orderByDesc('version_number')->orderByDesc('id'), $request, $versionsPage);
        $actions = $this->withParticipantSnapshots(MeetingWorkspacePagination::read($this->participantActions($meeting), $request, $actionsPage));

        $latestDirectorReview = null;
        $activeCase = $meeting->activeParticipantChangeCase;
        if ($activeCase && $activeCase->status === 'RETURNED_FOR_CORRECTION') {
            $review = $meeting->rescheduleActions()->with('actor')
                ->where('action', 'PARTICIPANT_CHANGE_RETURNED')->latest('id')->first();
            if ((int) ($review?->metadata['change_case_id'] ?? 0) === (int) $activeCase->id) {
                $latestDirectorReview = $review;
            }
        }

        return [
            'meeting' => new MeetingResource($meeting),
            'current_schedule_version' => $meeting->currentScheduleVersion,
            'current_participant_version' => $meeting->currentParticipantVersion,
            'current_participants' => $meeting->participants,
            'active_participant_change_case' => $meeting->activeParticipantChangeCase,
            'latest_director_review' => $latestDirectorReview,
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

    private function withParticipantSnapshots(array $page): array
    {
        $rows = $page['data'] ?? [];
        $employeeIds = collect($rows)->flatMap(function (array $action): array {
            $metadata = (array) ($action['metadata'] ?? []);
            $ids = [$metadata['old_employee_id'] ?? null, $metadata['new_employee_id'] ?? null];
            foreach (($metadata['operations'] ?? []) as $operation) {
                $ids[] = $operation['old_employee_id'] ?? null;
                $ids[] = $operation['new_employee_id'] ?? null;
            }
            return $ids;
        })->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($employeeIds->isEmpty()) return $page;

        $employees = Employee::query()->with('unit')->whereIn('id', $employeeIds)->get()->keyBy('id');
        $snapshot = static function ($id) use ($employees): ?array {
            $employee = $employees->get((int) $id);
            if (!$employee) return null;
            return [
                'id' => (int) $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'unit_name' => $employee->unit?->name,
            ];
        };

        foreach ($rows as &$action) {
            $metadata = (array) ($action['metadata'] ?? []);
            foreach (['old', 'new'] as $side) {
                $key = "{$side}_employee";
                $idKey = "{$side}_employee_id";
                if (!isset($metadata[$key]) && !empty($metadata[$idKey])) {
                    $metadata[$key] = $snapshot($metadata[$idKey]);
                }
            }
            if (isset($metadata['operations']) && is_array($metadata['operations'])) {
                foreach ($metadata['operations'] as &$operation) {
                    foreach (['old', 'new'] as $side) {
                        $key = "{$side}_employee";
                        $idKey = "{$side}_employee_id";
                        if (!isset($operation[$key]) && !empty($operation[$idKey])) {
                            $operation[$key] = $snapshot($operation[$idKey]);
                        }
                    }
                }
                unset($operation);
            }
            $action['metadata'] = $metadata;
        }
        unset($action);
        $page['data'] = $rows;

        return $page;
    }

    private function assertCaseBelongsToMeeting(MeetingParticipantChangeCase $case, Meeting $meeting): void
    {
        abort_unless((int) $case->meeting_id === (int) $meeting->id, 404, 'PARTICIPANT_CHANGE_CASE_NOT_FOUND');
    }
}
