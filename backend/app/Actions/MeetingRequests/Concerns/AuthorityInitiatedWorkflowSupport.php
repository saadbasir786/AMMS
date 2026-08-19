<?php

namespace App\Actions\MeetingRequests\Concerns;

use App\Enums\MeetingRequestStatus;
use App\Models\AmmsNotification;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\MeetingRequestAction;
use App\Models\MeetingRequestDirectiveVersion;
use App\Models\MeetingRequestUnitResponse;
use App\Models\OrganizationAuthority;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Collection;

trait AuthorityInitiatedWorkflowSupport
{
    private function lockFlow06Request(int|MeetingRequest $request): MeetingRequest
    {
        $id = $request instanceof MeetingRequest ? $request->id : $request;
        $locked = MeetingRequest::query()->lockForUpdate()->findOrFail($id);
        abort_unless($locked->flow_type === 'HIGH_AUTHORITY_INITIATED', 422, 'FLOW06_REQUEST_REQUIRED');

        return $locked->load([
            'requestingUnit.organization', 'units.unit', 'creator.employee', 'participants.employee.unit',
            'nominations.employee.unit', 'attachments', 'actions.actor', 'scheduleProposals.proposedByUnit',
            'scheduleProposals.proposedByUser', 'scheduleProposals.authorityUnitAcknowledgements.unit',
            'authorityContext.authorityOfficeUnit', 'authorityContext.authorityPosition',
            'authorityContext.authorityEmployee.position', 'authorityContext.authorityEmployee.unit',
            'authorityContext.currentDirectiveVersion', 'directiveVersions.preparedBy', 'directiveVersions.issuer',
            'directiveVersions.authorityEmployee.position', 'directiveVersions.authorityEmployee.unit',
            'unitResponses.unit', 'unitResponses.directiveVersion', 'unitResponses.coordinationStarter',
            'unitResponses.acknowledger', 'directiveClarifications.unit', 'directiveClarifications.requester',
            'directiveClarifications.responder', 'authorityScheduleUnitAcknowledgements.unit',
            'participantConfirmations.unit', 'authorityParticipantConfirmations.confirmedBy',
            'meeting.authorityContext.authorityEmployee', 'meeting.authorityContext.authorityPosition',
            'meeting.authorityContext.authorityOfficeUnit', 'meeting.units.unit', 'meeting.participants.employee.unit',
        ]);
    }

    private function assertActiveActor(User $actor): void
    {
        abort_unless($actor->isActive(), 403, 'INACTIVE_USER_NOT_ALLOWED');
    }

    private function flow06Units(MeetingRequest $request): Collection
    {
        return $request->units->where('role', 'PARTICIPATING')->pluck('unit')->filter()->values();
    }

    private function assertFlow06UnitSet(Collection $units): void
    {
        abort_unless($units->isNotEmpty(), 422, 'AUTHORITY_INITIATED_REQUIRES_DIRECTORATE');
        $ids = $units->pluck('id')->map(fn ($id) => (int) $id)->all();
        abort_unless(count($ids) === count(array_unique($ids)), 422, 'DUPLICATE_PARTICIPATING_DIRECTORATE');
        foreach ($units as $unit) {
            abort_unless($unit->status === 'ACTIVE' && $unit->unit_type === 'DIRECTORATE', 422, 'INVALID_PARTICIPATING_DIRECTORATE');
        }
        $organizationIds = $units->pluck('organization_id')->unique();
        abort_unless($organizationIds->count() === 1, 422, 'CROSS_ORGANIZATION_AUTHORITY_INITIATED_MEETING_NOT_ALLOWED');
    }

    private function authorityForOrganization(int $organizationId): OrganizationAuthority
    {
        $authority = OrganizationAuthority::with(['organization', 'authorityPosition.unit.organization', 'authorityOfficeUnit.organization'])
            ->where('organization_id', $organizationId)->where('status', 'ACTIVE')->where('is_primary', true)->first();
        abort_unless($authority, 422, 'HIGH_AUTHORITY_NOT_CONFIGURED');
        abort_unless($authority->authorityPosition?->status === 'ACTIVE' && $authority->authorityOfficeUnit?->status === 'ACTIVE', 422, 'HIGH_AUTHORITY_NOT_CONFIGURED');
        abort_unless($authority->authorityPosition->unit?->organization_id === $organizationId && $authority->authorityOfficeUnit->organization_id === $organizationId, 422, 'CROSS_ORGANIZATION_AUTHORITY_INITIATED_MEETING_NOT_ALLOWED');
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

    private function currentVersion(MeetingRequest $request): MeetingRequestDirectiveVersion
    {
        $version = $request->directiveVersions->firstWhere('is_current', true) ?: $request->directiveVersions->sortByDesc('version')->first();
        abort_unless($version, 422, 'DIRECTIVE_VERSION_OUTDATED');
        return $version;
    }

    private function transition06(MeetingRequest $request, MeetingRequestStatus $next, string $error = 'INVALID_STATUS_TRANSITION'): string
    {
        $current = MeetingRequestStatus::tryFrom($request->status);
        abort_unless($current && $current->canTransitionTo($next), 422, $error);
        $from = $request->status;
        $request->update(['status' => $next->value]);
        return $from;
    }

    private function record06(MeetingRequest $request, User $actor, string $action, ?string $from, ?string $to, ?string $comment = null, array $metadata = []): void
    {
        MeetingRequestAction::create([
            'meeting_request_id' => $request->id, 'actor_id' => $actor->id, 'action' => $action,
            'from_status' => $from, 'to_status' => $to, 'comment' => $comment, 'metadata' => $metadata ?: null,
        ]);
    }

    private function response06(MeetingRequest $request): MeetingRequest
    {
        return $request->fresh([
            'requestingUnit.organization', 'units.unit', 'creator.employee', 'participants.employee.unit',
            'nominations.employee.unit', 'attachments', 'actions.actor', 'scheduleProposals.proposedByUnit',
            'scheduleProposals.proposedByUser', 'scheduleProposals.authorityUnitAcknowledgements.unit',
            'authorityContext.authorityOfficeUnit', 'authorityContext.authorityPosition',
            'authorityContext.authorityEmployee.position', 'authorityContext.authorityEmployee.unit',
            'authorityContext.currentDirectiveVersion', 'directiveVersions.preparedBy', 'directiveVersions.issuer',
            'directiveVersions.authorityEmployee.position', 'directiveVersions.authorityEmployee.unit',
            'unitResponses.unit', 'unitResponses.directiveVersion', 'unitResponses.coordinationStarter',
            'unitResponses.acknowledger', 'directiveClarifications.unit', 'directiveClarifications.requester',
            'directiveClarifications.responder', 'authorityScheduleUnitAcknowledgements.unit',
            'participantConfirmations.unit', 'authorityParticipantConfirmations.confirmedBy', 'meeting.authorityContext.authorityEmployee',
            'meeting.authorityContext.authorityPosition', 'meeting.authorityContext.authorityOfficeUnit', 'meeting.units.unit', 'meeting.participants.employee.unit',
        ]);
    }

    private function notifyUser(?int $userId, string $title, string $message, MeetingRequest $request): void
    {
        if ($userId) AmmsNotification::create(['user_id' => $userId, 'type' => 'HIGH_AUTHORITY_INITIATED_MEETING', 'title' => $title, 'message' => $message, 'data' => ['meeting_request_id' => $request->id]]);
    }

    private function notifyUnit(int $unitId, array $roles, string $title, string $message, MeetingRequest $request): void
    {
        $users = User::query()->whereHas('roleScopes', function ($query) use ($unitId, $roles): void {
            $query->where('organizational_unit_id', $unitId)->whereHas('role', fn ($role) => $role->whereIn('name', $roles)->where('guard_name', 'sanctum'));
        })->get()->filter(fn (User $user) => $user->isActive());
        foreach ($users as $user) $this->notifyUser($user->id, $title, $message, $request);
    }

    private function actorCanPrepare(User $actor, int $officeUnitId): bool
    {
        return $actor->can('authority-initiated-meeting.prepare') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('AUTHORITY_OFFICE', $officeUnitId) || $actor->hasScopedRole('HIGH_AUTHORITY'));
    }

    private function actorIsHighAuthority(User $actor, Employee $holder): bool
    {
        return $actor->can('authority-initiated-meeting.issue') && $actor->employee_id && (int) $actor->employee_id === (int) $holder->id && $holder->status === 'ACTIVE';
    }

    private function actorOwnsDirectorate(User $actor, int $unitId): bool
    {
        return $actor->isActive() && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $actor->hasScopedRole('DIRECTOR', $unitId) || $actor->hasScopedRole('MEETING_COORDINATOR', $unitId));
    }

    private function actorCoordinates(User $actor, int $unitId): bool
    {
        return $actor->isActive() && $actor->can('authority-directive.coordinate') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $actor->hasScopedRole('MEETING_COORDINATOR', $unitId));
    }

    private function actorCanAcknowledge(User $actor, int $unitId): bool
    {
        return $actor->isActive() && $actor->can('authority-directive.acknowledge') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('DIRECTOR', $unitId));
    }

    private function actorCanClarify(User $actor, int $unitId): bool
    {
        return $actor->isActive() && $actor->can('authority-directive.clarification-request') && ($actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('DIRECTOR', $unitId));
    }

    private function nextNumber(string $name, string $prefix): string
    {
        $sequence = \App\Models\AmmsSequence::query()->lockForUpdate()->find($name);
        if (!$sequence) { \App\Models\AmmsSequence::create(['name' => $name, 'value' => 0]); $sequence = \App\Models\AmmsSequence::query()->lockForUpdate()->findOrFail($name); }
        $sequence->increment('value');
        return $prefix.'-'.now()->format('Y').'-'.str_pad((string) $sequence->fresh()->value, 5, '0', STR_PAD_LEFT);
    }

    private function assertEmployeesInUnit(array $employeeIds, OrganizationalUnit $unit): void
    {
        $ids = array_values(array_unique(array_map('intval', $employeeIds)));
        $employees = Employee::whereIn('id', $ids)->get();
        abort_if($employees->count() !== count($ids), 422, 'INVALID_PARTICIPANT');
        foreach ($employees as $employee) {
            abort_unless($employee->status === 'ACTIVE' && $unit->isWithinScope((int) $employee->organizational_unit_id), 422, 'PARTICIPANT_OUTSIDE_DIRECTORATE_SCOPE');
        }
    }

    private function allCurrentAcknowledged(MeetingRequest $request): bool
    {
        $version = $this->currentVersion($request);
        $ids = $this->flow06Units($request)->pluck('id')->map(fn ($id) => (int) $id);
        return $ids->isNotEmpty() && $request->unitResponses->where('meeting_request_directive_version_id', $version->id)->whereIn('organizational_unit_id', $ids)->count() === $ids->count() && $request->unitResponses->where('meeting_request_directive_version_id', $version->id)->whereIn('organizational_unit_id', $ids)->every(fn ($row) => $row->response_status === 'ACKNOWLEDGED');
    }
}
