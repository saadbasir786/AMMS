<?php

namespace App\Policies;

use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MeetingRequestPolicy
{
    public function before(User $user): ?bool
    {
        if (!$user->isActive()) return false;
        return $user->hasScopedRole('SYSTEM_ADMIN') && $user->can('meeting-request.view') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->can('meeting-request.view');
    }

    public function view(User $user, MeetingRequest $request): bool
    {
        if (!$user->isActive()) return false;
        if ($request->flow_type === 'HIGH_AUTHORITY_INITIATED') {
            $context = $request->authorityContext()->first();
            if (!$context) return false;
            if ($request->created_by === $user->id && ($user->can('authority-initiated-meeting.prepare') || $user->can('authority-directive.view'))) return true;
            if ($user->employee_id && (int) $context->authority_employee_id === (int) $user->employee_id && $user->can('authority-directive.view')) return true;
            $positionUnitId = (int) $context->authorityPosition()->value('organizational_unit_id');
            if ($user->can('authority-directive.view') && $positionUnitId && $user->hasScopedRole('HIGH_AUTHORITY', $positionUnitId)) return true;
            if ($user->can('authority-directive.view') && ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $context->authority_office_unit_id) || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $context->authority_office_unit_id))) return true;
            if ($request->status === 'DRAFT' || in_array($request->status, ['PENDING_HIGH_AUTHORITY_ISSUANCE', 'RETURNED_FOR_AUTHORITY_DRAFT_CORRECTION', 'ISSUED_BY_HIGH_AUTHORITY'], true)) return false;
            if ($user->can('authority-directive.view')) {
                return $request->units()->where('role', 'PARTICIPATING')->pluck('organizational_unit_id')->contains(fn ($unitId) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $unitId) || $user->hasScopedRole('DIRECTOR', (int) $unitId) || $user->hasScopedRole('MEETING_COORDINATOR', (int) $unitId));
            }
            return false;
        }
        if (in_array($request->flow_type, ['DIRECTORATE_WITH_HIGH_AUTHORITY', 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY'], true)) {
            if (!$user->can('meeting-request.view') && !$user->can('authority-request.view')) return false;
            if ($request->created_by === $user->id) return true;
            if ($user->can('meeting-request.view') && ($user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id) || $user->hasScopedRole('DIRECTOR', (int) $request->requesting_unit_id) || $user->hasScopedRole('MEETING_COORDINATOR', (int) $request->requesting_unit_id))) return true;
            if ($request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->can('meeting-request.view')) {
                $isFormalUnitMember = $request->units()->whereIn('role', ['REQUESTER', 'PARTICIPATING'])->pluck('organizational_unit_id')->contains(fn ($unitId) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $unitId) || $user->hasScopedRole('DIRECTOR', (int) $unitId) || $user->hasScopedRole('MEETING_COORDINATOR', (int) $unitId));
                if ($isFormalUnitMember) return true;
            }
            $context = $request->authorityContext()->first();
            if (!$context || !$user->can('authority-request.view')) return false;
            if ($user->employee_id && (int) $context->authority_employee_id === (int) $user->employee_id) return true;
            if ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $context->authority_office_unit_id) || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $context->authority_office_unit_id)) return true;
            return false;
        }
        $unitIds = in_array($request->flow_type, ['INTER_DIRECTORATE', 'MULTI_DIRECTORATE'], true) ? $request->units()->pluck('organizational_unit_id')->all() : [$request->requesting_unit_id];
        return collect(['DIRECTOR', 'MEETING_COORDINATOR', 'EXECUTIVE_MANAGEMENT'])->contains(fn (string $role) => collect($unitIds)->contains(fn (int $unitId) => $user->hasScopedRole($role, $unitId)));
    }

    public function viewIncoming(User $user, MeetingRequest $request): bool
    {
        if (!$user->isActive() || !$user->can('meeting-request.view-incoming') || !in_array($request->flow_type, ['INTER_DIRECTORATE', 'MULTI_DIRECTORATE'], true)) return false;
        return $request->units()->where('role', 'TARGET')->pluck('organizational_unit_id')->contains(fn ($targetId) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $targetId) || $user->hasScopedRole('DIRECTOR', $targetId) || $user->hasScopedRole('MEETING_COORDINATOR', $targetId));
    }

    public function flow02RequesterApprove(User $user, MeetingRequest $request): bool
    {
        $unitId = $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $user->isActive() && $user->can('meeting-request.approve') && $unitId && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function flow02RequesterEdit(User $user, MeetingRequest $request): bool
    {
        $unitId = $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $user->isActive() && $user->can('meeting-request.update') && $unitId && ($request->created_by === $user->id || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId)) && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'RETURNED_BY_TARGET'], true);
    }

    public function flow02TargetReview(User $user, MeetingRequest $request): bool
    {
        return $this->viewIncoming($user, $request);
    }

    public function flow02TargetApprove(User $user, MeetingRequest $request): bool
    {
        $unitId = $request->units()->where('role', 'TARGET')->value('organizational_unit_id');
        return $user->isActive() && $user->can('meeting-request.approve-target') && $unitId && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function flow02TargetReturn(User $user, MeetingRequest $request): bool
    {
        return $this->flow02TargetApprove($user, $request) && $user->can('meeting-request.return-target');
    }

    public function flow02TargetReject(User $user, MeetingRequest $request): bool
    {
        return $this->flow02TargetApprove($user, $request) && $user->can('meeting-request.reject-target');
    }

    public function flow03RequesterApprove(User $user, MeetingRequest $request): bool
    {
        $unitId = $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE' && $user->isActive() && $user->can('meeting-request.approve') && $unitId && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function flow03RequesterEdit(User $user, MeetingRequest $request): bool
    {
        $unitId = $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE' && $user->isActive() && $user->can('meeting-request.update') && $request->created_by === $user->id && $unitId && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'TARGET_ACTION_REQUIRED'], true);
    }

    public function flow03TargetReview(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE' && $user->isActive() && $user->can('meeting-request.view-incoming') && $request->units()->where('role', 'TARGET')->where('organizational_unit_id', $unitId)->exists() && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    public function flow03TargetApprove(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE' && $user->isActive() && $user->can('meeting-request.approve-target') && $request->units()->where('role', 'TARGET')->where('organizational_unit_id', $unitId)->exists() && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function flow03TargetReturn(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $this->flow03TargetApprove($user, $request, $unitId) && $user->can('meeting-request.return-target');
    }

    public function flow03TargetReject(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $this->flow03TargetApprove($user, $request, $unitId) && $user->can('meeting-request.reject-target');
    }

    public function flow03Coordinate(User $user, MeetingRequest $request, ?int $unitId = null): bool
    {
        if ($request->flow_type !== 'MULTI_DIRECTORATE' || !$user->isActive() || !$user->can('meeting-schedule.propose')) return false;
        $unitIds = $request->units()->pluck('organizational_unit_id')->all();
        if ($unitId !== null && !in_array($unitId, $unitIds, true)) return false;
        return collect($unitId !== null ? [$unitId] : $unitIds)->contains(fn (int $id) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $id) || $user->hasScopedRole('MEETING_COORDINATOR', $id));
    }

    public function flow03ParticipantConfirm(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE' && $user->isActive() && $user->can('meeting-participant.confirm') && $request->units()->whereIn('role', ['REQUESTER', 'TARGET'])->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $user->hasScopedRole('MEETING_COORDINATOR', $unitId));
    }

    public function flow02Coordinate(User $user, MeetingRequest $request): bool
    {
        $unitIds = $request->units()->pluck('organizational_unit_id')->all();
        return $user->isActive() && $user->can('meeting-schedule.propose') && collect($unitIds)->contains(fn (int $unitId) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $user->hasScopedRole('MEETING_COORDINATOR', $unitId));
    }

    public function flow04RequesterEdit(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.update') && $request->created_by === $user->id && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $request->requesting_unit_id) && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'RETURNED_BY_AUTHORITY_OFFICE', 'RETURNED_BY_HIGH_AUTHORITY'], true);
    }

    public function flow04RequesterApprove(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.approve') && $user->hasScopedRole('DIRECTOR', $request->requesting_unit_id);
    }

    public function flow04OfficeReview(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-request.review') && $officeId && ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId) || $user->hasScopedRole('SYSTEM_ADMIN'));
    }

    public function flow04OfficeForward(User $user, MeetingRequest $request): bool
    {
        return $this->flow04OfficeReview($user, $request) && $user->can('authority-request.forward');
    }

    public function flow04AuthorityDecision(User $user, MeetingRequest $request): bool
    {
        $employeeId = $request->authorityContext()->value('authority_employee_id');
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-request.decide') && $employeeId && (int) $user->employee_id === (int) $employeeId;
    }

    public function flow04Scheduler(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId && $user->can('authority-schedule.view') && ($user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $officeId) || $user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId));
    }

    public function flow04RequesterScheduleAcknowledge(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-schedule.acknowledge') && ($request->created_by === $user->id || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $request->requesting_unit_id) || $user->hasScopedRole('MEETING_COORDINATOR', $request->requesting_unit_id));
    }

    public function flow04RequesterParticipants(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-participant.confirm') && ($request->created_by === $user->id || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $request->requesting_unit_id) || $user->hasScopedRole('MEETING_COORDINATOR', $request->requesting_unit_id));
    }

    public function flow04AuthorityParticipants(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId && $user->can('authority-participant.manage') && ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId) || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $officeId));
    }

    public function flow04Finalize(User $user, MeetingRequest $request): bool
    {
        return $this->flow04Scheduler($user, $request) && $user->can('authority-meeting.finalize');
    }

    public function flow05RequesterEdit(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.update') && $request->created_by === $user->id && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id) && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'DIRECTORATE_ACTION_REQUIRED', 'RETURNED_BY_AUTHORITY_OFFICE', 'RETURNED_BY_HIGH_AUTHORITY'], true);
    }

    public function flow05RequesterApprove(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.approve') && $user->hasScopedRole('DIRECTOR', (int) $request->requesting_unit_id);
    }

    public function flow05UnitReview(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.view-incoming') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    public function flow05UnitApprove(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.approve-target') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function flow05Coordinate(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && ($user->hasScopedRole('SYSTEM_ADMIN') || $user->id === $request->lead_coordinator_user_id || $user->hasScopedRole('MEETING_COORDINATOR', (int) $request->requesting_unit_id));
    }

    public function flow05AuthorityOfficeReview(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-request.review') && $officeId && ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId) || $user->hasScopedRole('SYSTEM_ADMIN'));
    }

    public function flow05AuthorityDecision(User $user, MeetingRequest $request): bool
    {
        $employeeId = $request->authorityContext()->value('authority_employee_id');
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-request.decide') && $employeeId && (int) $user->employee_id === (int) $employeeId;
    }

    public function flow05Schedule(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId && $user->can('authority-schedule.view') && ($user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $officeId) || $user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId));
    }

    public function flow05ScheduleAcknowledge(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-schedule.acknowledge') && $request->units()->whereIn('role', ['REQUESTER', 'PARTICIPATING'])->pluck('organizational_unit_id')->contains(fn ($unitId) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $unitId) || $user->hasScopedRole('DIRECTOR', (int) $unitId) || $user->hasScopedRole('MEETING_COORDINATOR', (int) $unitId));
    }

    public function flow05UnitParticipants(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $request->units()->whereIn('role', ['REQUESTER', 'PARTICIPATING'])->where('organizational_unit_id', $unitId)->exists() && $user->can('meeting-participant.confirm') && ($user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $user->hasScopedRole('MEETING_COORDINATOR', $unitId));
    }

    public function flow05AuthorityParticipants(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId && $user->can('authority-participant.manage') && ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId) || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $officeId));
    }

    public function flow05Finalize(User $user, MeetingRequest $request): bool
    {
        return $this->flow05Schedule($user, $request) && $user->can('authority-meeting.finalize');
    }

    public function flow06Prepare(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-initiated-meeting.prepare') && $officeId && ($request->created_by === $user->id || $user->hasScopedRole('SYSTEM_ADMIN') || $user->hasScopedRole('AUTHORITY_OFFICE', $officeId));
    }

    public function flow06Issue(User $user, MeetingRequest $request): bool
    {
        $employeeId = (int) $request->authorityContext()->value('authority_employee_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-initiated-meeting.issue') && (!$employeeId || (int) $user->employee_id === $employeeId);
    }

    public function flow06Distribute(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-initiated-meeting.distribute') && $officeId && ($user->hasScopedRole('SYSTEM_ADMIN') || $user->hasScopedRole('AUTHORITY_OFFICE', $officeId));
    }

    public function flow06Coordinate(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->can('authority-directive.coordinate') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('SYSTEM_ADMIN') || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $user->hasScopedRole('MEETING_COORDINATOR', $unitId));
    }

    public function flow06Acknowledge(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->can('authority-directive.acknowledge') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('SYSTEM_ADMIN') || $user->hasScopedRole('DIRECTOR', $unitId));
    }

    public function flow06Clarify(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->can('authority-directive.clarification-request') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('SYSTEM_ADMIN') || $user->hasScopedRole('DIRECTOR', $unitId));
    }

    public function flow06ClarificationRespond(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->can('authority-directive.clarification-respond') && $officeId && ($user->hasScopedRole('SYSTEM_ADMIN') || $user->hasScopedRole('AUTHORITY_OFFICE', $officeId));
    }

    public function flow06Schedule(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-schedule.view') && $officeId && ($user->hasScopedRole('SYSTEM_ADMIN') || $user->hasScopedRole('EXECUTIVE_SECRETARY', $officeId));
    }

    public function flow06ScheduleAcknowledge(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-schedule.acknowledge') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('SYSTEM_ADMIN') || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $user->hasScopedRole('DIRECTOR', $unitId) || $user->hasScopedRole('MEETING_COORDINATOR', $unitId));
    }

    public function flow06UnitParticipants(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('meeting-participant.confirm') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('SYSTEM_ADMIN') || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $user->hasScopedRole('DIRECTOR', $unitId) || $user->hasScopedRole('MEETING_COORDINATOR', $unitId));
    }

    public function flow06AuthorityParticipants(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-participant.manage') && $officeId && ($user->hasScopedRole('SYSTEM_ADMIN') || $user->hasScopedRole('AUTHORITY_OFFICE', $officeId));
    }

    public function flow06Finalize(User $user, MeetingRequest $request): bool
    {
        return $this->flow06Schedule($user, $request) && $user->can('authority-meeting.finalize');
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->can('meeting-request.create') && (filled($user->scopedRoleUnitIds('EXECUTIVE_MANAGEMENT')) || filled($user->scopedRoleUnitIds('MEETING_COORDINATOR')));
    }

    public function update(User $user, MeetingRequest $request): bool
    {
        return $user->isActive() && $user->can('meeting-request.update') && $request->created_by === $user->id && (in_array($request->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true) || ($request->flow_type === 'INTER_DIRECTORATE' && in_array($request->status, ['RETURNED_TO_REQUESTER', 'RETURNED_BY_TARGET'], true)) || ($request->flow_type === 'MULTI_DIRECTORATE' && in_array($request->status, ['RETURNED_TO_REQUESTER', 'TARGET_ACTION_REQUIRED'], true)));
    }

    public function submit(User $user, MeetingRequest $request): bool
    {
        return $user->isActive() && ($user->can('meeting-request.submit') || $user->can('meeting-request.resubmit')) && $request->created_by === $user->id && in_array($request->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true);
    }

    public function review(User $user, MeetingRequest $request): Response|bool
    {
        if (!$user->isActive()) return Response::deny('UNAUTHORIZED');
        if ($request->status !== 'PENDING_INTERNAL_APPROVAL') return Response::deny('REQUEST_NOT_PENDING_APPROVAL');
        if (!$user->can('meeting-request.approve') && !$user->can('meeting-request.return') && !$user->can('meeting-request.reject')) return Response::deny('FORBIDDEN');
        if (!$user->hasScopedRole('DIRECTOR', $request->requesting_unit_id)) return Response::deny('UNAUTHORIZED_APPROVER');
        return true;
    }

    public function schedule(User $user, MeetingRequest $request): bool
    {
        return $user->isActive() && $user->can('meeting.schedule') && in_array($request->status, ['APPROVED', 'SCHEDULING', 'SCHEDULE_CONFIRMED'], true) && ($request->created_by === $user->id || $user->hasScopedRole('MEETING_COORDINATOR', $request->requesting_unit_id));
    }

    public function finalize(User $user, MeetingRequest $request): bool
    {
        return $user->isActive() && $user->can('meeting.create') && ($request->created_by === $user->id || $user->hasScopedRole('MEETING_COORDINATOR', $request->requesting_unit_id));
    }

    public function delete(User $user, MeetingRequest $request): bool
    {
        return $user->isActive() && $user->can('meeting-request.update') && $request->created_by === $user->id && $request->status === 'DRAFT';
    }
}
