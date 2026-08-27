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
use App\Http\Resources\MeetingResource;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingParticipantChangeCase;
use App\Models\MeetingParticipantChangeItem;
use App\Models\OrganizationalUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Support\ParticipantGovernanceResolver;

class MeetingParticipantChangeController
{
    public function workspace(Request $request, Meeting $meeting): JsonResponse
    {
        Gate::authorize('flow08ViewHistory', $meeting);
        return response()->json(['data' => $this->workspaceData($meeting, $request->user())]);
    }

    public function createCase(Request $request, Meeting $meeting, CreateMeetingParticipantChangeCase $action): JsonResponse
    {
        Gate::authorize('flow08CreateCase', $meeting);
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
        Gate::authorize('flow08ViewHistory', $meeting);
        $meeting->load(['participantVersions.members.employee.unit', 'participantVersions.sourceChangeCase', 'rescheduleActions.actor']);
        return response()->json(['data' => [
            'meeting_id' => $meeting->id,
            'current_participant_version' => $meeting->currentParticipantVersion,
            'participant_versions' => $meeting->participantVersions->sortBy('version_number')->values(),
            'actions' => $meeting->rescheduleActions->filter(fn ($action) => str_starts_with($action->action, 'PARTICIPANT_CHANGE') || $action->action === 'MEETING_PARTICIPANTS_CHANGED')->sortBy('created_at')->values(),
        ]]);
    }

    private function workspaceData(Meeting $meeting, $actor): array
    {
        $meeting->load([
            'request', 'units.unit', 'participants.employee.unit', 'chairperson', 'authorityContext.authorityEmployee',
            'currentScheduleVersion', 'currentParticipantVersion.members.employee.unit',
            'activeParticipantChangeCase.items.oldEmployee.unit', 'activeParticipantChangeCase.items.newEmployee.unit',
            'activeParticipantChangeCase.ownerUnit', 'activeParticipantChangeCase.baselineScheduleVersion',
            'participantVersions.members.employee.unit', 'rescheduleActions.actor',
        ]);
        return [
            'meeting' => new MeetingResource($meeting),
            'current_schedule_version' => $meeting->currentScheduleVersion,
            'current_participant_version' => $meeting->currentParticipantVersion,
            'current_participants' => $meeting->participants,
            'active_participant_change_case' => $meeting->activeParticipantChangeCase,
            'participant_versions' => $meeting->participantVersions->sortBy('version_number')->values(),
            'participant_actions' => $meeting->rescheduleActions->filter(fn ($action) => str_starts_with($action->action, 'PARTICIPANT_CHANGE') || $action->action === 'MEETING_PARTICIPANTS_CHANGED')->sortBy('created_at')->values(),
            'employee_options' => $this->employeeOptions($meeting, $actor),
        ];
    }

    private function employeeOptions(Meeting $meeting, $actor)
    {
        $owner = app(ParticipantGovernanceResolver::class)->actorOwner($meeting, $actor);
        if (!$owner) return [];
        $employees = Employee::with(['unit', 'position'])->where('status', 'ACTIVE')->get();
        if ($owner['owner_type'] === 'AUTHORITY') {
            $officeId = (int) ($meeting->authorityContext?->authority_office_unit_id ?? 0);
            $authorityId = (int) ($meeting->authorityContext?->authority_employee_id ?? 0);
            return $employees->filter(fn (Employee $employee): bool => (int) $employee->id === $authorityId || ($officeId > 0 && $employee->unit?->isWithinScope($officeId)))->values();
        }
        $unit = OrganizationalUnit::with('children')->find($owner['owner_unit_id']);
        return $unit ? $employees->filter(fn (Employee $employee): bool => $unit->isWithinScope((int) $employee->organizational_unit_id))->values() : [];
    }

    private function assertCaseBelongsToMeeting(MeetingParticipantChangeCase $case, Meeting $meeting): void
    {
        abort_unless((int) $case->meeting_id === (int) $meeting->id, 404, 'PARTICIPANT_CHANGE_CASE_NOT_FOUND');
    }
}
