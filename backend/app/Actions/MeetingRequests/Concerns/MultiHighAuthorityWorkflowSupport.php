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

trait MultiHighAuthorityWorkflowSupport
{
    private function lockFlow05Request(int|MeetingRequest $request): MeetingRequest
    {
        $id = $request instanceof MeetingRequest ? $request->id : $request;
        $locked = MeetingRequest::query()->lockForUpdate()->findOrFail($id);
        abort_unless($locked->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY', 422, 'FLOW05_REQUEST_REQUIRED');

        return $locked->load([
            'requestingUnit.organization', 'units.unit', 'creator.employee', 'participants.employee.unit',
            'nominations.employee.unit', 'attachments', 'actions.actor', 'unitReviews.unit',
            'participantConfirmations.unit', 'scheduleProposals.proposedByUnit', 'scheduleProposals.proposedByUser',
            'scheduleProposals.authorityUnitAcknowledgements.unit', 'authorityContext.authorityOfficeUnit',
            'authorityContext.authorityPosition', 'authorityContext.authorityEmployee.position',
            'authorityContext.authorityEmployee.unit', 'authorityContext.officeReviewer',
            'authorityContext.authorityDecider', 'authorityScheduleUnitAcknowledgements.unit',
            'authorityParticipantConfirmations.confirmedBy', 'meeting.authorityContext.authorityEmployee',
            'meeting.authorityContext.authorityPosition', 'meeting.authorityContext.authorityOfficeUnit',
            'meeting.units.unit', 'meeting.participants.employee.unit',
        ]);
    }

    private function assertActiveActor(User $actor): void
    {
        abort_unless($actor->isActive(), 403, 'INACTIVE_USER_NOT_ALLOWED');
    }

    private function requesterUnit(MeetingRequest $request): OrganizationalUnit
    {
        return $request->units->firstWhere('role', 'REQUESTER')?->unit ?? abort(422, 'EXACTLY_ONE_REQUESTER_REQUIRED');
    }

    private function participatingUnits(MeetingRequest $request): Collection
    {
        return $request->units->where('role', 'PARTICIPATING')->pluck('unit')->filter()->values();
    }

    private function formalUnits(MeetingRequest $request): Collection
    {
        return $request->units->whereIn('role', ['REQUESTER', 'PARTICIPATING'])->pluck('unit')->filter()->values();
    }

    private function assertUnitSet(Collection $rows): void
    {
        $requesters = $rows->where('role', 'REQUESTER');
        $participants = $rows->where('role', 'PARTICIPATING');
        abort_unless($requesters->count() === 1, 422, 'EXACTLY_ONE_REQUESTER_REQUIRED');
        abort_unless($participants->count() >= 1, 422, 'MULTIPLE_DIRECTORATES_REQUIRED_FOR_FLOW_05');
        $requester = $requesters->first()->unit;
        abort_unless($requester && $requester->status === 'ACTIVE' && $requester->unit_type === 'DIRECTORATE', 422, 'INVALID_REQUESTER_DIRECTORATE');
        $ids = $rows->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->all();
        abort_if(count($ids) !== count(array_unique($ids)), 422, 'DUPLICATE_PARTICIPATING_DIRECTORATE');
        abort_if($participants->contains(fn ($row) => (int) $row->organizational_unit_id === (int) $requester->id), 422, 'REQUESTER_CANNOT_BE_PARTICIPATING');
        foreach ($rows->pluck('unit')->filter() as $unit) {
            abort_unless($unit->status === 'ACTIVE' && $unit->unit_type === 'DIRECTORATE', 422, 'INVALID_PARTICIPATING_DIRECTORATE');
            abort_unless((int) $unit->organization_id === (int) $requester->organization_id, 422, 'CROSS_ORGANIZATION_HIGH_AUTHORITY_MEETING_NOT_ALLOWED');
        }
    }

    private function activeAuthorityFor(MeetingRequest $request): OrganizationAuthority
    {
        $organizationId = $this->requesterUnit($request)->organization_id;
        $authority = OrganizationAuthority::with(['organization', 'authorityPosition.unit.organization', 'authorityOfficeUnit.organization'])
            ->where('organization_id', $organizationId)->where('status', 'ACTIVE')->where('is_primary', true)->first();
        abort_unless($authority, 422, 'HIGH_AUTHORITY_NOT_CONFIGURED');
        abort_unless($authority->authorityPosition?->status === 'ACTIVE', 422, 'INVALID_HIGH_AUTHORITY_HOLDER');
        abort_unless($authority->authorityOfficeUnit?->status === 'ACTIVE', 422, 'AUTHORITY_OFFICE_NOT_CONFIGURED');
        abort_unless($authority->authorityPosition->unit?->organization_id === $organizationId, 422, 'CROSS_ORGANIZATION_HIGH_AUTHORITY_MEETING_NOT_ALLOWED');
        return $authority;
    }

    private function currentAuthorityHolder(OrganizationAuthority $authority): Employee
    {
        $holder = Employee::with(['position', 'unit'])->where('position_id', $authority->authority_position_id)
            ->where('status', 'ACTIVE')->whereHas('position', fn ($q) => $q->where('status', 'ACTIVE'))
            ->whereHas('unit', fn ($q) => $q->where('status', 'ACTIVE'))->first();
        abort_unless($holder, 422, 'HIGH_AUTHORITY_HOLDER_NOT_AVAILABLE');
        return $holder;
    }

    private function persistedHolder(MeetingRequest $request): Employee
    {
        $context = $request->authorityContext;
        abort_unless($context?->authority_employee_id, 422, 'HIGH_AUTHORITY_PARTICIPANT_REQUIRED');
        $holder = Employee::with(['position', 'unit'])->find($context->authority_employee_id);
        abort_unless($holder && $holder->status === 'ACTIVE' && $holder->position_id === $context->authority_position_id && $holder->position?->status === 'ACTIVE' && $holder->unit?->status === 'ACTIVE', 422, 'HIGH_AUTHORITY_PARTICIPANT_UNAVAILABLE');
        return $holder;
    }

    private function canManageUnit(User $actor, int $unitId): bool
    {
        return $actor->hasScopedRole('SYSTEM_ADMIN') || ($actor->can('meeting-request.create') && $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId));
    }

    private function canRequesterApprove(User $actor, int $unitId): bool
    {
        return $actor->can('meeting-request.approve') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('DIRECTOR', $unitId));
    }

    private function canParticipatingUnit(User $actor, int $unitId): bool
    {
        return $actor->isActive() && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $actor->hasScopedRole('DIRECTOR', $unitId));
    }

    private function canAuthorityOffice(User $actor, int $officeUnitId): bool
    {
        return $actor->can('authority-request.review') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('AUTHORITY_OFFICE', $officeUnitId));
    }

    private function canScheduler(User $actor, int $officeUnitId): bool
    {
        return $actor->can('authority-schedule.propose') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('EXECUTIVE_SECRETARY', $officeUnitId) || $actor->hasScopedRole('AUTHORITY_OFFICE', $officeUnitId));
    }

    private function canHighAuthority(User $actor, Employee $holder, OrganizationAuthority $authority): bool
    {
        return $actor->can('authority-request.decide') && $actor->employee_id === $holder->id && $holder->status === 'ACTIVE' && $holder->position_id === $authority->authority_position_id;
    }

    private function transition(MeetingRequest $request, MeetingRequestStatus $next, string $code = 'INVALID_STATUS_TRANSITION'): string
    {
        $current = MeetingRequestStatus::tryFrom($request->status);
        abort_unless($current && $current->canTransitionTo($next), 422, $code);
        $from = $request->status;
        $request->update(['status' => $next->value]);
        return $from;
    }

    private function record(MeetingRequest $request, User $actor, string $action, ?string $from, ?string $to, ?string $comment = null, array $metadata = []): void
    {
        MeetingRequestAction::create(['meeting_request_id' => $request->id, 'actor_id' => $actor->id, 'action' => $action, 'from_status' => $from, 'to_status' => $to, 'comment' => $comment, 'metadata' => $metadata ?: null]);
    }

    private function notifyUnit(int $unitId, array $roles, string $title, string $message, MeetingRequest $request): void
    {
        $users = User::query()->whereHas('roleScopes', function ($query) use ($unitId, $roles): void {
            $query->where('organizational_unit_id', $unitId)->whereHas('role', fn ($role) => $role->whereIn('name', $roles)->where('guard_name', 'sanctum'));
        })->get()->filter(fn (User $user) => $user->isActive());
        foreach ($users as $user) AmmsNotification::create(['user_id' => $user->id, 'type' => 'MULTI_HIGH_AUTHORITY_MEETING_REQUEST', 'title' => $title, 'message' => $message, 'data' => ['meeting_request_id' => $request->id, 'organizational_unit_id' => $unitId]]);
    }

    private function notifyUser(?int $userId, string $title, string $message, MeetingRequest $request): void
    {
        if ($userId) AmmsNotification::create(['user_id' => $userId, 'type' => 'MULTI_HIGH_AUTHORITY_MEETING_REQUEST', 'title' => $title, 'message' => $message, 'data' => ['meeting_request_id' => $request->id]]);
    }

    private function assertEmployeesInUnit(array $employeeIds, OrganizationalUnit $unit): void
    {
        $ids = array_values(array_unique(array_map('intval', $employeeIds)));
        $employees = Employee::whereIn('id', $ids)->get();
        abort_if($employees->count() !== count($ids), 422, 'INVALID_PARTICIPANT');
        foreach ($employees as $employee) {
            abort_unless($employee->status === 'ACTIVE' && $unit->isWithinScope((int) $employee->organizational_unit_id), 422, 'PARTICIPANT_OUTSIDE_MULTI_DIRECTORATE_SCOPE');
        }
    }

    private function allParticipatingApproved(MeetingRequest $request): bool
    {
        $ids = $this->participatingUnits($request)->pluck('id')->map(fn ($id) => (int) $id);
        return $ids->isNotEmpty() && $request->unitReviews->whereIn('organizational_unit_id', $ids)->count() === $ids->count() && $request->unitReviews->whereIn('organizational_unit_id', $ids)->every(fn ($review) => $review->review_status === 'APPROVED');
    }

    private function nextNumber(string $name, string $prefix): string
    {
        $sequence = \App\Models\AmmsSequence::query()->lockForUpdate()->find($name);
        if (!$sequence) { \App\Models\AmmsSequence::create(['name' => $name, 'value' => 0]); $sequence = \App\Models\AmmsSequence::query()->lockForUpdate()->findOrFail($name); }
        $sequence->increment('value');
        return $prefix.'-'.now()->format('Y').'-'.str_pad((string) $sequence->fresh()->value, 5, '0', STR_PAD_LEFT);
    }

    private function responseRequest(MeetingRequest $request): MeetingRequest
    {
        return $request->fresh([
            'requestingUnit.organization', 'units.unit', 'creator.employee', 'participants.employee.unit', 'nominations.employee.unit',
            'attachments', 'actions.actor', 'unitReviews.unit', 'participantConfirmations.unit', 'scheduleProposals.proposedByUnit',
            'scheduleProposals.proposedByUser', 'scheduleProposals.authorityUnitAcknowledgements.unit', 'authorityContext.authorityOfficeUnit',
            'authorityContext.authorityPosition', 'authorityContext.authorityEmployee.position', 'authorityContext.authorityEmployee.unit',
            'authorityContext.officeReviewer', 'authorityContext.authorityDecider', 'authorityScheduleUnitAcknowledgements.unit',
            'authorityParticipantConfirmations.confirmedBy', 'meeting.authorityContext.authorityEmployee', 'meeting.authorityContext.authorityPosition',
            'meeting.authorityContext.authorityOfficeUnit', 'meeting.units.unit', 'meeting.participants.employee.unit',
        ]);
    }
}
