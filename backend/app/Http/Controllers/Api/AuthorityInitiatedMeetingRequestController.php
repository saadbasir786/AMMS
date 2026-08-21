<?php

namespace App\Http\Controllers\Api;

use App\Actions\MeetingRequests\AcknowledgeAuthorityInitiatedSchedule;
use App\Actions\MeetingRequests\AcknowledgeAuthorityMeetingDirective;
use App\Actions\MeetingRequests\ConfirmAuthorityInitiatedAuthorityParticipants;
use App\Actions\MeetingRequests\ConfirmAuthorityInitiatedSchedule;
use App\Actions\MeetingRequests\ConfirmAuthorityInitiatedUnitParticipants;
use App\Actions\MeetingRequests\ConvertAuthorityInitiatedRequestToMeeting;
use App\Actions\MeetingRequests\CreateAuthorityInitiatedDraft;
use App\Actions\MeetingRequests\DistributeAuthorityMeetingDirective;
use App\Actions\MeetingRequests\IssueHighAuthorityMeetingDirective;
use App\Actions\MeetingRequests\ManageAuthorityInitiatedAuthorityParticipants;
use App\Actions\MeetingRequests\ManageAuthorityInitiatedUnitParticipants;
use App\Actions\MeetingRequests\PrepareAuthorityDirectiveRevision;
use App\Actions\MeetingRequests\ProposeAuthorityInitiatedSchedule;
use App\Actions\MeetingRequests\RequestAuthorityInitiatedScheduleAlternative;
use App\Actions\MeetingRequests\RequestDirectiveClarification;
use App\Actions\MeetingRequests\ResubmitAuthorityInitiatedDraft;
use App\Actions\MeetingRequests\ReissueHighAuthorityMeetingDirective;
use App\Actions\MeetingRequests\RespondToDirectiveClarification;
use App\Actions\MeetingRequests\ReturnAuthorityInitiatedDraftForCorrection;
use App\Actions\MeetingRequests\StartAuthorityDirectiveCoordination;
use App\Actions\MeetingRequests\StartAuthorityInitiatedScheduling;
use App\Actions\MeetingRequests\SubmitAuthorityInitiatedDraftForIssuance;
use App\Actions\MeetingRequests\UpdateAuthorityInitiatedDraft;
use App\Http\Resources\MeetingRequestResource;
use App\Http\Resources\MeetingResource;
use App\Models\MeetingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuthorityInitiatedMeetingRequestController
{
    public function store(Request $request, CreateAuthorityInitiatedDraft $action): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'], 'purpose' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'], 'directive' => ['required', 'string'],
            'meeting_justification' => ['nullable', 'string'], 'requested_outcome' => ['required', 'string'],
            'proposed_agenda' => ['nullable', 'string'], 'priority' => ['required', 'in:NORMAL,HIGH,URGENT'],
            'preferred_date' => ['nullable', 'date'], 'preferred_start_time' => ['nullable', 'date_format:H:i'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'], 'preferred_location' => ['nullable', 'string', 'max:255'],
            'participating_unit_ids' => ['required', 'array', 'min:1'], 'participating_unit_ids.*' => ['integer', 'distinct'],
        ]);
        return $this->ok($action($request->user(), $data))->setStatusCode(201);
    }

    public function update(Request $request, MeetingRequest $meetingRequest, UpdateAuthorityInitiatedDraft $action): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['sometimes', 'required', 'string', 'max:255'], 'purpose' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['nullable', 'string'], 'directive' => ['sometimes', 'required', 'string'],
            'meeting_justification' => ['nullable', 'string'], 'requested_outcome' => ['sometimes', 'required', 'string'],
            'proposed_agenda' => ['nullable', 'string'], 'priority' => ['sometimes', 'in:NORMAL,HIGH,URGENT'],
            'preferred_date' => ['nullable', 'date'], 'preferred_start_time' => ['nullable', 'date_format:H:i'],
            'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'], 'preferred_location' => ['nullable', 'string', 'max:255'],
            'participating_unit_ids' => ['sometimes', 'array', 'min:1'], 'participating_unit_ids.*' => ['integer', 'distinct'],
        ]);
        Gate::authorize('flow06Prepare', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $data));
    }

    public function submit(Request $request, MeetingRequest $meetingRequest, SubmitAuthorityInitiatedDraftForIssuance $action): JsonResponse
    {
        Gate::authorize('flow06Prepare', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest));
    }

    public function returnDraft(Request $request, MeetingRequest $meetingRequest, ReturnAuthorityInitiatedDraftForCorrection $action): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string']])['reason'];
        Gate::authorize('flow06Issue', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $reason));
    }

    public function resubmit(Request $request, MeetingRequest $meetingRequest, ResubmitAuthorityInitiatedDraft $action): JsonResponse
    {
        Gate::authorize('flow06Prepare', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest));
    }

    public function issue(Request $request, MeetingRequest $meetingRequest, IssueHighAuthorityMeetingDirective $action): JsonResponse
    {
        Gate::authorize('flow06Issue', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest));
    }

    public function distribute(Request $request, MeetingRequest $meetingRequest, DistributeAuthorityMeetingDirective $action): JsonResponse
    {
        Gate::authorize('flow06Distribute', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest));
    }

    public function startCoordination(Request $request, MeetingRequest $meetingRequest, int $unit, StartAuthorityDirectiveCoordination $action): JsonResponse
    {
        Gate::authorize('flow06Coordinate', [$meetingRequest, $unit]);
        return $this->ok($action($request->user(), $meetingRequest, $unit));
    }

    public function acknowledge(Request $request, MeetingRequest $meetingRequest, int $unit, AcknowledgeAuthorityMeetingDirective $action): JsonResponse
    {
        Gate::authorize('flow06Acknowledge', [$meetingRequest, $unit]);
        return $this->ok($action($request->user(), $meetingRequest, $unit));
    }

    public function requestClarification(Request $request, MeetingRequest $meetingRequest, int $unit, RequestDirectiveClarification $action): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string']])['reason'];
        Gate::authorize('flow06Clarify', [$meetingRequest, $unit]);
        return $this->ok($action($request->user(), $meetingRequest, $unit, $reason));
    }

    public function respondClarification(Request $request, MeetingRequest $meetingRequest, int $clarification, RespondToDirectiveClarification $action): JsonResponse
    {
        $responseText = $request->validate(['response' => ['required', 'string']])['response'];
        Gate::authorize('flow06ClarificationRespond', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $clarification, $responseText));
    }

    public function prepareRevision(Request $request, MeetingRequest $meetingRequest, PrepareAuthorityDirectiveRevision $action): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'], 'purpose' => ['nullable', 'string', 'max:50'], 'description' => ['nullable', 'string'],
            'directive' => ['nullable', 'string'], 'meeting_justification' => ['nullable', 'string'], 'requested_outcome' => ['nullable', 'string'],
            'proposed_agenda' => ['nullable', 'string'], 'priority' => ['nullable', 'in:NORMAL,HIGH,URGENT'], 'preferred_date' => ['nullable', 'date'],
            'preferred_start_time' => ['nullable', 'date_format:H:i'], 'estimated_duration_minutes' => ['nullable', 'integer', 'min:1'],
            'preferred_location' => ['nullable', 'string', 'max:255'], 'participating_unit_ids' => ['nullable', 'array', 'min:1'], 'participating_unit_ids.*' => ['integer', 'distinct'],
        ]);
        Gate::authorize('flow06Prepare', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $data));
    }

    public function reissue(Request $request, MeetingRequest $meetingRequest, ReissueHighAuthorityMeetingDirective $action): JsonResponse
    {
        Gate::authorize('flow06Issue', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest));
    }

    public function startScheduling(Request $request, MeetingRequest $meetingRequest, StartAuthorityInitiatedScheduling $action): JsonResponse
    {
        Gate::authorize('flow06Schedule', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest));
    }

    public function proposeSchedule(Request $request, MeetingRequest $meetingRequest, ProposeAuthorityInitiatedSchedule $action): JsonResponse
    {
        $data = $request->validate(['meeting_date' => ['required', 'date'], 'start_time' => ['required', 'date_format:H:i'], 'end_time' => ['required', 'date_format:H:i'], 'location' => ['required', 'string', 'max:255'], 'comment' => ['nullable', 'string']]);
        Gate::authorize('flow06Schedule', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $data));
    }

    public function acknowledgeSchedule(Request $request, MeetingRequest $meetingRequest, AcknowledgeAuthorityInitiatedSchedule $action): JsonResponse
    {
        $data = $request->validate(['proposal_id' => ['required', 'integer'], 'unit_id' => ['required', 'integer']]);
        Gate::authorize('flow06ScheduleAcknowledge', [$meetingRequest, (int) $data['unit_id']]);
        return $this->ok($action($request->user(), $meetingRequest, (int) $data['unit_id'], (int) $data['proposal_id']));
    }

    public function scheduleAlternative(Request $request, MeetingRequest $meetingRequest, RequestAuthorityInitiatedScheduleAlternative $action): JsonResponse
    {
        $data = $request->validate(['proposal_id' => ['required', 'integer'], 'unit_id' => ['required', 'integer'], 'reason' => ['required', 'string']]);
        Gate::authorize('flow06ScheduleAcknowledge', [$meetingRequest, (int) $data['unit_id']]);
        return $this->ok($action($request->user(), $meetingRequest, (int) $data['unit_id'], (int) $data['proposal_id'], $data['reason']));
    }

    public function confirmSchedule(Request $request, MeetingRequest $meetingRequest, ConfirmAuthorityInitiatedSchedule $action): JsonResponse
    {
        Gate::authorize('flow06Schedule', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest));
    }

    public function unitParticipants(Request $request, MeetingRequest $meetingRequest, int $unit, ManageAuthorityInitiatedUnitParticipants $action): JsonResponse
    {
        $ids = $request->validate(['employee_ids' => ['required', 'array'], 'employee_ids.*' => ['integer', 'distinct']])['employee_ids'];
        Gate::authorize('flow06UnitParticipants', [$meetingRequest, $unit]);
        return $this->ok($action($request->user(), $meetingRequest, $unit, $ids));
    }

    public function unitParticipantsConfirm(Request $request, MeetingRequest $meetingRequest, int $unit, ConfirmAuthorityInitiatedUnitParticipants $action): JsonResponse
    {
        Gate::authorize('flow06UnitParticipants', [$meetingRequest, $unit]);
        return $this->ok($action($request->user(), $meetingRequest, $unit));
    }

    public function authorityParticipants(Request $request, MeetingRequest $meetingRequest, ManageAuthorityInitiatedAuthorityParticipants $action): JsonResponse
    {
        $ids = $request->validate(['employee_ids' => ['required', 'array'], 'employee_ids.*' => ['integer', 'distinct']])['employee_ids'];
        Gate::authorize('flow06AuthorityParticipants', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest, $ids));
    }

    public function authorityParticipantsConfirm(Request $request, MeetingRequest $meetingRequest, ConfirmAuthorityInitiatedAuthorityParticipants $action): JsonResponse
    {
        Gate::authorize('flow06AuthorityParticipants', $meetingRequest);
        return $this->ok($action($request->user(), $meetingRequest));
    }

    public function finalize(Request $request, MeetingRequest $meetingRequest, ConvertAuthorityInitiatedRequestToMeeting $action): JsonResponse
    {
        $data = $request->validate(['agenda' => ['nullable', 'string']]);
        Gate::authorize('flow06Finalize', $meetingRequest);
        return response()->json(['data' => new MeetingResource($action($request->user(), $meetingRequest, $data))]);
    }

    public function officeInbox(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isActive() && $user->can('authority-directive.view'), 403, 'FORBIDDEN');

        $officeUnitIds = array_values(array_unique(array_filter(array_map('intval', array_merge(
            $user->scopedRoleUnitIds('AUTHORITY_OFFICE'),
            $user->scopedRoleUnitIds('EXECUTIVE_SECRETARY'),
        )))));
        $employeeId = (int) $user->employee_id;
        abort_unless($officeUnitIds !== [] || $employeeId > 0, 403, 'FORBIDDEN');

        $query = MeetingRequest::query()
            ->where('flow_type', 'HIGH_AUTHORITY_INITIATED')
            ->whereHas('authorityContext', function ($q) use ($officeUnitIds, $employeeId) {
                $q->where(function ($scope) use ($officeUnitIds, $employeeId) {
                    if ($officeUnitIds !== []) {
                        $scope->whereIn('authority_office_unit_id', $officeUnitIds);
                    }
                    if ($employeeId > 0) {
                        if ($officeUnitIds !== []) $scope->orWhere('authority_employee_id', $employeeId);
                        else $scope->where('authority_employee_id', $employeeId);
                    }
                });
            })
            ->with(['units.unit', 'authorityContext', 'creator']);

        return response()->json(['data' => MeetingRequestResource::collection($query->latest()->paginate(25))]);
    }

    private function ok(MeetingRequest $request): JsonResponse
    {
        return response()->json(['data' => new MeetingRequestResource($request)]);
    }
}
