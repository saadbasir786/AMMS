<?php

namespace App\Policies;

use App\Models\MeetingRequest;
use App\Models\User;

class FlowAwareMeetingRequestPolicy extends MeetingRequestPolicy
{
    public function view(User $user, MeetingRequest $request): bool
    {
        if (in_array($request->flow_type, ['INTER_DIRECTORATE', 'MULTI_DIRECTORATE'], true)) {
            return $user->isActive() && $user->can('meeting-request.view') && parent::view($user, $request);
        }

        if ($request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY') {
            if (!$user->isActive()) return false;
            if ($user->can('meeting-request.view') && ($user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id) || $user->hasScopedRole('DIRECTOR', (int) $request->requesting_unit_id))) return true;
            $context = $request->authorityContext()->first();
            if (!$context || !$user->can('authority-request.view')) return false;
            return ($user->employee_id && (int) $context->authority_employee_id === (int) $user->employee_id)
                || $user->hasScopedRole('AUTHORITY_OFFICE', (int) $context->authority_office_unit_id)
                || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $context->authority_office_unit_id);
        }

        if ($request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY') {
            if (!$user->isActive()) return false;
            if ($user->can('meeting-request.view')) {
                $formal = $request->units()->whereIn('role', ['REQUESTER', 'PARTICIPATING'])->pluck('organizational_unit_id');
                if ($formal->contains(fn ($id) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $id) || $user->hasScopedRole('DIRECTOR', (int) $id))) return true;
            }
            $context = $request->authorityContext()->first();
            if (!$context || !$user->can('authority-request.view')) return false;
            return ($user->employee_id && (int) $context->authority_employee_id === (int) $user->employee_id)
                || $user->hasScopedRole('AUTHORITY_OFFICE', (int) $context->authority_office_unit_id)
                || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $context->authority_office_unit_id);
        }

        if ($request->flow_type === 'HIGH_AUTHORITY_INITIATED') {
            if (!$user->isActive() || !$user->can('authority-directive.view')) return false;
            $context = $request->authorityContext()->first();
            if (!$context) return false;
            if ($this->flow06HighAuthorityActor($user, $request)) return true;
            if ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $context->authority_office_unit_id) || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $context->authority_office_unit_id)) return true;
            if (in_array($request->status, ['DRAFT', 'PENDING_HIGH_AUTHORITY_ISSUANCE', 'RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION', 'ISSUED_BY_HIGH_AUTHORITY'], true)) return false;
            return $request->units()->where('role', 'PARTICIPATING')->pluck('organizational_unit_id')->contains(fn ($id) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $id) || $user->hasScopedRole('DIRECTOR', (int) $id));
        }

        return parent::view($user, $request);
    }

    public function update(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTERNAL_DIRECTORATE') return parent::update($user, $request);
        return $user->isActive() && $user->can('meeting-request.update') && $request->created_by === $user->id
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
            && in_array($request->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true);
    }

    public function submit(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTERNAL_DIRECTORATE') return parent::submit($user, $request);
        return $user->isActive() && ($user->can('meeting-request.submit') || $user->can('meeting-request.resubmit'))
            && $request->created_by === $user->id && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
            && in_array($request->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true);
    }

    public function schedule(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTERNAL_DIRECTORATE') return parent::schedule($user, $request);
        return $user->isActive() && $user->can('meeting.schedule') && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
            && in_array($request->status, ['APPROVED', 'SCHEDULING'], true);
    }

    public function finalize(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTERNAL_DIRECTORATE') return parent::finalize($user, $request);
        return $user->isActive() && $user->can('meeting.create') && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
            && $request->status === 'SCHEDULE_CONFIRMED';
    }

    public function delete(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTERNAL_DIRECTORATE') return parent::delete($user, $request);
        return $user->isActive() && $user->can('meeting-request.update') && $request->created_by === $user->id
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id) && $request->status === 'DRAFT';
    }

    public function flow02RequesterEdit(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTER_DIRECTORATE') return false;
        $unitId = (int) $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $user->isActive() && $user->can('meeting-request.update') && $unitId > 0
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId)
            && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'RETURNED_BY_TARGET'], true);
    }

    public function flow02Coordinate(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTER_DIRECTORATE' || !$user->isActive() || !$user->can('meeting-schedule.propose')) return false;
        $unitIds = $request->units()->pluck('organizational_unit_id')->map(fn ($id) => (int) $id)->all();
        return collect($unitIds)->contains(fn (int $unitId) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId));
    }

    public function flow02LeadCoordinate(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTER_DIRECTORATE' || !$user->isActive()) return false;
        if (!$user->can('meeting-schedule.propose') && !$user->can('meeting-schedule.accept') && !$user->can('meeting.create')) return false;
        $requesterId = (int) $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $requesterId > 0 && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $requesterId);
    }

    public function flow03RequesterEdit(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'MULTI_DIRECTORATE') return false;
        $unitId = (int) $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $user->isActive() && $user->can('meeting-request.update') && $unitId > 0
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId)
            && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'TARGET_ACTION_REQUIRED'], true);
    }

    public function flow03LeadCoordinate(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'MULTI_DIRECTORATE' || !$user->isActive()) return false;
        if (!$user->can('meeting-schedule.propose') && !$user->can('meeting-schedule.accept') && !$user->can('meeting.create')) return false;
        $requesterId = (int) $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $requesterId > 0 && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $requesterId);
    }

    public function flow04RequesterEdit(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.update')
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
            && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'RETURNED_BY_AUTHORITY_OFFICE', 'RETURNED_BY_HIGH_AUTHORITY'], true);
    }

    public function flow04Scheduler(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId > 0
            && $user->can('authority-schedule.view') && $user->hasScopedRole('EXECUTIVE_SECRETARY', $officeId);
    }

    public function flow04RequesterScheduleAcknowledge(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive()
            && $user->can('authority-schedule.acknowledge')
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id);
    }

    public function flow04RequesterParticipants(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive()
            && $user->can('meeting-participant.confirm')
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id);
    }

    public function flow04AuthorityParticipants(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId > 0
            && $user->hasScopedRole('AUTHORITY_OFFICE', $officeId)
            && ($user->can('authority-participant.manage') || $user->can('authority-participant.confirm'));
    }

    public function flow05RequesterEdit(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.update')
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
            && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'DIRECTORATE_ACTION_REQUIRED', 'RETURNED_BY_AUTHORITY_OFFICE', 'RETURNED_BY_HIGH_AUTHORITY'], true);
    }

    public function flow05Coordinate(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive()
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id);
    }

    public function flow05Schedule(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId > 0
            && $user->can('authority-schedule.view') && $user->hasScopedRole('EXECUTIVE_SECRETARY', $officeId);
    }

    public function flow05UnitParticipantsManage(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive()
            && $request->units()->whereIn('role', ['REQUESTER', 'PARTICIPATING'])->where('organizational_unit_id', $unitId)->exists()
            && $user->can('meeting-participant.nominate') && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    public function flow05UnitParticipantsConfirm(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive()
            && $request->units()->whereIn('role', ['REQUESTER', 'PARTICIPATING'])->where('organizational_unit_id', $unitId)->exists()
            && $user->can('meeting-participant.confirm') && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function flow05AuthorityParticipants(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId > 0
            && $user->hasScopedRole('AUTHORITY_OFFICE', $officeId)
            && ($user->can('authority-participant.manage') || $user->can('authority-participant.confirm'));
    }

    public function flow06Prepare(User $user, MeetingRequest $request): bool
    {
        $context = $request->authorityContext()->first();
        if ($request->flow_type !== 'HIGH_AUTHORITY_INITIATED' || !$user->isActive() || !$context) return false;
        return $user->can('authority-initiated-meeting.prepare')
            && ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $context->authority_office_unit_id)
                || $this->flow06HighAuthorityActor($user, $request));
    }

    public function flow06Issue(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive()
            && $user->can('authority-initiated-meeting.issue') && $this->flow06HighAuthorityActor($user, $request);
    }

    public function flow06UnitParticipantsManage(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive()
            && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists()
            && $user->can('meeting-participant.nominate') && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    public function flow06UnitParticipantsConfirm(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive()
            && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists()
            && $user->can('meeting-participant.confirm') && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function flow06AuthorityParticipants(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $officeId > 0
            && $user->hasScopedRole('AUTHORITY_OFFICE', $officeId)
            && ($user->can('authority-participant.manage') || $user->can('authority-participant.confirm'));
    }

    private function flow06HighAuthorityActor(User $user, MeetingRequest $request): bool
    {
        $context = $request->authorityContext()->first();
        if (!$context || !$user->employee_id) return false;
        if ($context->authority_employee_id) {
            return (int) $context->authority_employee_id === (int) $user->employee_id;
        }
        $employee = $user->employee;
        $positionUnitId = (int) $context->authorityPosition()->value('organizational_unit_id');
        return $employee?->status === 'ACTIVE'
            && (int) $employee->position_id === (int) $context->authority_position_id
            && $positionUnitId > 0
            && $user->hasScopedRole('HIGH_AUTHORITY', $positionUnitId);
    }
}