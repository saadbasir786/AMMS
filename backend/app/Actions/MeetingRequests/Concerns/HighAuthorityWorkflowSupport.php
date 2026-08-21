<?php

namespace App\Actions\MeetingRequests\Concerns;

use App\Enums\MeetingRequestStatus;
use App\Models\AmmsNotification;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\MeetingRequestAction;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Collection;

trait HighAuthorityWorkflowSupport
{
    private function lockFlow04Request(int|MeetingRequest $request): MeetingRequest
    {
        $id = $request instanceof MeetingRequest ? $request->id : $request;
        $locked = MeetingRequest::query()->lockForUpdate()->findOrFail($id);
        abort_unless($locked->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY', 422, 'FLOW04_REQUEST_REQUIRED');
        return $locked->load([
            'requestingUnit.organization', 'creator.employee', 'participants.employee.unit', 'attachments', 'actions.actor',
            'scheduleProposals.proposedByUnit', 'scheduleProposals.proposedByUser', 'scheduleProposals.authorityAcknowledgements',
            'authorityContext.authorityOfficeUnit', 'authorityContext.authorityPosition', 'authorityContext.authorityEmployee.position',
            'authorityContext.authorityEmployee.unit', 'authorityContext.officeReviewer', 'authorityContext.authorityDecider',
            'authorityScheduleAcknowledgements', 'authorityParticipantConfirmations.confirmedBy',
            'meeting.authorityContext.authorityEmployee', 'meeting.authorityContext.authorityPosition',
            'meeting.authorityContext.authorityOfficeUnit', 'meeting.units.unit', 'meeting.participants.employee.unit',
        ]);
    }

    private function assertActiveActor(User $actor): void
    {
        abort_unless($actor->isActive(), 403, 'UNAUTHORIZED');
    }

    private function requesterUnit(MeetingRequest $request): OrganizationalUnit
    {
        return $request->requestingUnit ?? abort(422, 'INVALID_REQUESTING_DIRECTORATE');
    }

    private function activeAuthorityFor(MeetingRequest $request): OrganizationAuthority
    {
        $requester = $request->requestingUnit?->load('organization');
        $organization = $requester?->organization;
        abort_unless($organization && $organization->status === 'ACTIVE', 422, 'HIGH_AUTHORITY_NOT_CONFIGURED');
        $authority = OrganizationAuthority::with(['organization', 'authorityPosition.unit.organization', 'authorityOfficeUnit.organization'])
            ->where('organization_id', $organization->id)->where('status', 'ACTIVE')->where('is_primary', true)->first();
        abort_unless($authority, 422, 'HIGH_AUTHORITY_NOT_CONFIGURED');
        abort_unless($authority->authorityPosition && $authority->authorityPosition->status === 'ACTIVE', 422, 'INVALID_HIGH_AUTHORITY_HOLDER');
        abort_unless($authority->authorityOfficeUnit && $authority->authorityOfficeUnit->status === 'ACTIVE', 422, 'AUTHORITY_OFFICE_NOT_CONFIGURED');
        abort_unless($authority->authorityPosition->unit?->organization_id === $organization->id, 422, 'INVALID_HIGH_AUTHORITY_HOLDER');
        abort_unless($authority->authorityOfficeUnit->organization_id === $organization->id, 422, 'AUTHORITY_OFFICE_NOT_CONFIGURED');
        return $authority;
    }

    private function currentAuthorityHolder(OrganizationAuthority $authority): Employee
    {
        $holder = Employee::with(['position', 'unit.organization'])
            ->where('position_id', $authority->authority_position_id)->where('status', 'ACTIVE')
            ->whereHas('position', fn ($q) => $q->where('status', 'ACTIVE'))
            ->whereHas('unit', fn ($q) => $q->where('status', 'ACTIVE'))->first();
        abort_unless($holder, 422, 'HIGH_AUTHORITY_HOLDER_NOT_AVAILABLE');
        abort_unless($holder->position_id === $authority->authority_position_id, 422, 'INVALID_HIGH_AUTHORITY_HOLDER');
        return $holder;
    }

    private function assertPersistedHolderAvailable(MeetingRequest $request): Employee
    {
        $context = $request->authorityContext;
        abort_unless($context && $context->authority_employee_id, 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');
        $holder = Employee::with(['position', 'unit'])->find($context->authority_employee_id);
        abort_unless($holder && $holder->status === 'ACTIVE', 422, 'HIGH_AUTHORITY_PARTICIPANT_UNAVAILABLE');
        abort_unless($holder->position_id === $context->authority_position_id, 422, 'HIGH_AUTHORITY_PARTICIPANT_UNAVAILABLE');
        abort_unless($holder->position?->status === 'ACTIVE' && $holder->unit?->status === 'ACTIVE', 422, 'HIGH_AUTHORITY_PARTICIPANT_UNAVAILABLE');
        return $holder;
    }

    private function canManageRequester(User $actor, int $unitId): bool
    {
        return $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    private function canRequesterApprove(User $actor, int $unitId): bool
    {
        return $actor->can('meeting-request.approve') && $actor->hasScopedRole('DIRECTOR', $unitId);
    }

    private function canAuthorityOffice(User $actor, int $officeUnitId): bool
    {
        return $actor->can('authority-request.review') && $actor->hasScopedRole('AUTHORITY_OFFICE', $officeUnitId);
    }

    private function canStartAuthorityScheduling(User $actor, int $officeUnitId): bool
    {
        return $actor->can('authority-schedule.view') && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeUnitId);
    }

    private function canAuthorityScheduler(User $actor, int $officeUnitId): bool
    {
        return $actor->can('authority-schedule.propose') && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeUnitId);
    }

    private function canHighAuthority(User $actor, Employee $holder, OrganizationAuthority $authority): bool
    {
        return $actor->can('authority-request.decide')
            && $actor->employee_id === $holder->id
            && $holder->status === 'ACTIVE'
            && $holder->position_id === $authority->authority_position_id
            && $holder->unit?->organization_id === $authority->organization_id;
    }

    private function assertTransition(MeetingRequest $request, MeetingRequestStatus $next, string $code = 'INVALID_STATUS_TRANSITION'): void
    {
        $current = MeetingRequestStatus::tryFrom($request->status);
        abort_unless($current && $current->canTransitionTo($next), 422, $code);
    }

    private function updateStatus(MeetingRequest $request, MeetingRequestStatus $next): string
    {
        $from = $request->status;
        $request->update(['status' => $next->value]);
        return $from;
    }

    private function record(MeetingRequest $request, User $actor, string $action, ?string $from, ?string $to, ?string $comment = null, array $metadata = []): void
    {
        MeetingRequestAction::create(['meeting_request_id' => $request->id, 'actor_id' => $actor->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'comment' => $comment, 'metadata' => $metadata ?: null]);
    }

    private function notifyUsersForUnit(int $unitId, array $roles, string $title, string $message, MeetingRequest $request): void
    {
        $users = User::query()->whereHas('roleScopes', function ($query) use ($unitId, $roles): void {
            $query->where('organizational_unit_id', $unitId)->whereHas('role', fn ($role) => $role->whereIn('name', $roles)->where('guard_name', 'sanctum'));
        })->get()->filter(fn (User $user) => $user->isActive());
        foreach ($users as $user) AmmsNotification::create(['user_id' => $user->id, 'type' => 'HIGH_AUTHORITY_MEETING_REQUEST', 'title' => $title, 'message' => $message, 'data' => ['meeting_request_id' => $request->id]]);
    }

    private function notifyUser(?int $userId, string $title, string $message, MeetingRequest $request): void
    {
        if ($userId) AmmsNotification::create(['user_id' => $userId, 'type' => 'HIGH_AUTHORITY_MEETING_REQUEST', 'title' => $title, 'message' => $message, 'data' => ['meeting_request_id' => $request->id]]);
    }

    private function assertRequesterParticipants(MeetingRequest $request, array $employeeIds): void
    {
        $requester = $this->requesterUnit($request)->load('children');
        $ids = array_values(array_unique(array_map('intval', $employeeIds)));
        $employees = Employee::with(['unit.parent', 'position'])->whereIn('id', $ids)->get();
        abort_if($employees->count() !== count($ids), 422, 'INVALID_PARTICIPANT');
        foreach ($employees as $employee) {
            abort_unless($employee->status === 'ACTIVE', 422, 'INVALID_PARTICIPANT');
            abort_unless($requester->isWithinScope((int) $employee->organizational_unit_id), 422, 'PARTICIPANT_OUTSIDE_REQUESTER_SCOPE');
        }
    }

    private function nextNumber(string $name, string $prefix): string
    {
        $sequence = \App\Models\AmmsSequence::query()->lockForUpdate()->find($name);
        if (!$sequence) {
            \App\Models\AmmsSequence::create(['name' => $name, 'value' => 0]);
            $sequence = \App\Models\AmmsSequence::query()->lockForUpdate()->findOrFail($name);
        }
        $sequence->increment('value');
        return $prefix.'-'.now()->format('Y').'-'.str_pad((string) $sequence->fresh()->value, 5, '0', STR_PAD_LEFT);
    }

    private function responseRequest(MeetingRequest $request): MeetingRequest
    {
        return $request->fresh([
            'requestingUnit.organization', 'creator.employee', 'participants.employee.unit', 'attachments', 'actions.actor',
            'scheduleProposals.proposedByUnit', 'scheduleProposals.proposedByUser', 'scheduleProposals.authorityAcknowledgements',
            'authorityContext.authorityOfficeUnit', 'authorityContext.authorityPosition', 'authorityContext.authorityEmployee.position',
            'authorityContext.authorityEmployee.unit', 'authorityContext.officeReviewer', 'authorityContext.authorityDecider',
            'authorityScheduleAcknowledgements', 'authorityParticipantConfirmations.confirmedBy',
            'meeting.authorityContext.authorityEmployee', 'meeting.authorityContext.authorityPosition',
            'meeting.authorityContext.authorityOfficeUnit', 'meeting.units.unit', 'meeting.participants.employee.unit',
        ]);
    }
}
