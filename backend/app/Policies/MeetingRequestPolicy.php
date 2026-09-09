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
        return null;
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
                return $request->units()->where('role', 'PARTICIPATING')->pluck('organizational_unit_id')->contains(fn ($unitId) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $unitId) || $user->hasScopedRole('DIRECTOR', (int) $unitId));
            }
            return false;
        }
        if (in_array($request->flow_type, ['DIRECTORATE_WITH_HIGH_AUTHORITY', 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY'], true)) {
            if (!$user->can('meeting-request.view') && !$user->can('authority-request.view')) return false;
            if ($request->created_by === $user->id) return true;
            if ($user->can('meeting-request.view') && ($user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id) || $user->hasScopedRole('DIRECTOR', (int) $request->requesting_unit_id) || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id))) return true;
            if ($request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->can('meeting-request.view')) {
                $isFormalUnitMember = $request->units()->whereIn('role', ['REQUESTER', 'PARTICIPATING'])->pluck('organizational_unit_id')->contains(fn ($unitId) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $unitId) || $user->hasScopedRole('DIRECTOR', (int) $unitId));
                if ($isFormalUnitMember) return true;
            }
            $context = $request->authorityContext()->first();
            if (!$context || !$user->can('authority-request.view')) return false;
            if ($user->employee_id && (int) $context->authority_employee_id === (int) $user->employee_id) return true;
            if ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $context->authority_office_unit_id) || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $context->authority_office_unit_id)) return true;
            return false;
        }
        $unitIds = in_array($request->flow_type, ['INTER_DIRECTORATE', 'MULTI_DIRECTORATE'], true) ? $request->units()->pluck('organizational_unit_id')->all() : [$request->requesting_unit_id];
        return collect(['DIRECTOR', 'DEPUTY_DIRECTOR', 'EXECUTIVE_MANAGEMENT'])->contains(fn (string $role) => collect($unitIds)->contains(fn (int $unitId) => $user->hasScopedRole($role, $unitId)));
    }

    public function viewIncoming(User $user, MeetingRequest $request): bool
    {
        if (!$user->isActive() || !$user->can('meeting-request.view-incoming') || !in_array($request->flow_type, ['INTER_DIRECTORATE', 'MULTI_DIRECTORATE'], true)) return false;
        return $request->units()->where('role', 'TARGET')->pluck('organizational_unit_id')->contains(fn ($targetId) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $targetId) || $user->hasScopedRole('DIRECTOR', $targetId));
    }

    public function interDirectorateRequesterApprove(User $user, MeetingRequest $request): bool
    {
        $unitId = $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $user->isActive() && $user->can('meeting-request.approve') && $unitId && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function interDirectorateRequesterEdit(User $user, MeetingRequest $request): bool
    {
        $unitId = $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $user->isActive() && $user->can('meeting-request.update') && $unitId && ($request->created_by === $user->id || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId)) && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'RETURNED_BY_TARGET'], true);
    }

    public function interDirectorateTargetReview(User $user, MeetingRequest $request): bool
    {
        return $this->viewIncoming($user, $request);
    }

    public function interDirectorateTargetApprove(User $user, MeetingRequest $request): bool
    {
        $unitId = $request->units()->where('role', 'TARGET')->value('organizational_unit_id');
        return $user->isActive() && $user->can('meeting-request.approve-target') && $unitId && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function interDirectorateTargetReturn(User $user, MeetingRequest $request): bool
    {
        return $this->interDirectorateTargetApprove($user, $request) && $user->can('meeting-request.return-target');
    }

    public function interDirectorateTargetReject(User $user, MeetingRequest $request): bool
    {
        return $this->interDirectorateTargetApprove($user, $request) && $user->can('meeting-request.reject-target');
    }

    public function multiDirectorateRequesterApprove(User $user, MeetingRequest $request): bool
    {
        $unitId = $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE' && $user->isActive() && $user->can('meeting-request.approve') && $unitId && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function multiDirectorateRequesterEdit(User $user, MeetingRequest $request): bool
    {
        $unitId = $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE' && $user->isActive() && $user->can('meeting-request.update') && $request->created_by === $user->id && $unitId && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'TARGET_ACTION_REQUIRED'], true);
    }

    public function multiDirectorateTargetReview(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE' && $user->isActive() && $user->can('meeting-request.view-incoming') && $request->units()->where('role', 'TARGET')->where('organizational_unit_id', $unitId)->exists() && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    public function multiDirectorateTargetApprove(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE' && $user->isActive() && $user->can('meeting-request.approve-target') && $request->units()->where('role', 'TARGET')->where('organizational_unit_id', $unitId)->exists() && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function multiDirectorateTargetReturn(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $this->multiDirectorateTargetApprove($user, $request, $unitId) && $user->can('meeting-request.return-target');
    }

    public function multiDirectorateTargetReject(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $this->multiDirectorateTargetApprove($user, $request, $unitId) && $user->can('meeting-request.reject-target');
    }

    public function multiDirectorateCoordinate(User $user, MeetingRequest $request, ?int $unitId = null): bool
    {
        if ($request->flow_type !== 'MULTI_DIRECTORATE' || !$user->isActive() || !$user->can('meeting-schedule.propose')) return false;
        $unitIds = $request->units()->pluck('organizational_unit_id')->all();
        if ($unitId !== null && !in_array($unitId, $unitIds, true)) return false;
        return collect($unitId !== null ? [$unitId] : $unitIds)->contains(fn (int $id) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $id));
    }

    public function multiDirectorateParticipantConfirm(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE' && $user->isActive() && $user->can('meeting-participant.confirm') && $request->units()->whereIn('role', ['REQUESTER', 'TARGET'])->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId));
    }

    public function interDirectorateCoordinate(User $user, MeetingRequest $request): bool
    {
        $unitIds = $request->units()->pluck('organizational_unit_id')->all();
        return $user->isActive() && $user->can('meeting-schedule.propose') && collect($unitIds)->contains(fn (int $unitId) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId));
    }

    public function highAuthorityRequesterEdit(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.update') && $request->created_by === $user->id && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $request->requesting_unit_id) && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'RETURNED_BY_AUTHORITY_OFFICE', 'RETURNED_BY_HIGH_AUTHORITY'], true);
    }

    public function highAuthorityRequesterApprove(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.approve') && $user->hasScopedRole('DIRECTOR', $request->requesting_unit_id);
    }

    public function highAuthorityOfficeReview(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-request.review') && $officeId && ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId));
    }

    public function highAuthorityOfficeForward(User $user, MeetingRequest $request): bool
    {
        return $this->highAuthorityOfficeReview($user, $request) && $user->can('authority-request.forward');
    }

    public function highAuthorityAuthorityDecision(User $user, MeetingRequest $request): bool
    {
        $employeeId = $request->authorityContext()->value('authority_employee_id');
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-request.decide') && $employeeId && (int) $user->employee_id === (int) $employeeId;
    }

    public function highAuthorityScheduler(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId && $user->can('authority-schedule.view') && ($user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $officeId) || $user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId));
    }

    public function highAuthorityRequesterScheduleAcknowledge(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-schedule.acknowledge') && ($request->created_by === $user->id || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $request->requesting_unit_id));
    }

    public function highAuthorityRequesterParticipants(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-participant.confirm') && ($request->created_by === $user->id || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $request->requesting_unit_id));
    }

    public function highAuthorityParticipants(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId && $user->can('authority-participant.manage') && ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId) || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $officeId));
    }

    public function highAuthorityFinalize(User $user, MeetingRequest $request): bool
    {
        return $this->highAuthorityScheduler($user, $request) && $user->can('authority-meeting.finalize');
    }

    public function multiHighAuthorityRequesterEdit(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.update') && $request->created_by === $user->id && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id) && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'DIRECTORATE_ACTION_REQUIRED', 'RETURNED_BY_AUTHORITY_OFFICE', 'RETURNED_BY_HIGH_AUTHORITY'], true);
    }

    public function multiHighAuthorityRequesterApprove(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.approve') && $user->hasScopedRole('DIRECTOR', (int) $request->requesting_unit_id);
    }

    public function multiHighAuthorityUnitReview(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.view-incoming') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    public function multiHighAuthorityUnitApprove(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('meeting-request.approve-target') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && $user->hasScopedRole('DIRECTOR', $unitId);
    }

    public function multiHighAuthorityCoordinate(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && ($user->id === $request->lead_coordinator_user_id || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id));
    }

    public function multiHighAuthorityAuthorityOfficeReview(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-request.review') && $officeId && ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId));
    }

    public function multiHighAuthorityAuthorityDecision(User $user, MeetingRequest $request): bool
    {
        $employeeId = $request->authorityContext()->value('authority_employee_id');
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-request.decide') && $employeeId && (int) $user->employee_id === (int) $employeeId;
    }

    public function multiHighAuthoritySchedule(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId && $user->can('authority-schedule.view') && ($user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $officeId) || $user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId));
    }

    public function multiHighAuthorityScheduleAcknowledge(User $user, MeetingRequest $request): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $user->can('authority-schedule.acknowledge') && $request->units()->whereIn('role', ['REQUESTER', 'PARTICIPATING'])->pluck('organizational_unit_id')->contains(fn ($unitId) => $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $unitId) || $user->hasScopedRole('DIRECTOR', (int) $unitId));
    }

    public function multiHighAuthorityUnitParticipants(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $request->units()->whereIn('role', ['REQUESTER', 'PARTICIPATING'])->where('organizational_unit_id', $unitId)->exists() && $user->can('meeting-participant.confirm') && ($user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId));
    }

    public function multiHighAuthorityParticipants(User $user, MeetingRequest $request): bool
    {
        $officeId = $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY' && $user->isActive() && $officeId && $user->can('authority-participant.manage') && ($user->hasScopedRole('AUTHORITY_OFFICE', (int) $officeId) || $user->hasScopedRole('EXECUTIVE_SECRETARY', (int) $officeId));
    }

    public function multiHighAuthorityFinalize(User $user, MeetingRequest $request): bool
    {
        return $this->multiHighAuthoritySchedule($user, $request) && $user->can('authority-meeting.finalize');
    }

    public function authorityInitiatedPrepare(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-initiated-meeting.prepare') && $officeId && ($request->created_by === $user->id || $user->hasScopedRole('AUTHORITY_OFFICE', $officeId));
    }

    public function authorityInitiatedIssue(User $user, MeetingRequest $request): bool
    {
        $employeeId = (int) $request->authorityContext()->value('authority_employee_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-initiated-meeting.issue') && (!$employeeId || (int) $user->employee_id === $employeeId);
    }

    public function authorityInitiatedDistribute(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-initiated-meeting.distribute') && $officeId && ($user->hasScopedRole('AUTHORITY_OFFICE', $officeId));
    }

    public function authorityInitiatedCoordinate(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->can('authority-directive.coordinate') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId));
    }

    public function authorityInitiatedAcknowledge(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->can('authority-directive.acknowledge') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('DIRECTOR', $unitId));
    }

    public function authorityInitiatedClarify(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->can('authority-directive.clarification-request') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('DIRECTOR', $unitId));
    }

    public function authorityInitiatedClarificationRespond(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->can('authority-directive.clarification-respond') && $officeId && ($user->hasScopedRole('AUTHORITY_OFFICE', $officeId));
    }

    public function authorityInitiatedSchedule(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-schedule.view') && $officeId && ($user->hasScopedRole('EXECUTIVE_SECRETARY', $officeId));
    }

    public function authorityInitiatedScheduleAcknowledge(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-schedule.acknowledge') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $user->hasScopedRole('DIRECTOR', $unitId));
    }

    public function authorityInitiatedUnitParticipants(User $user, MeetingRequest $request, int $unitId): bool
    {
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('meeting-participant.confirm') && $request->units()->where('role', 'PARTICIPATING')->where('organizational_unit_id', $unitId)->exists() && ($user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId) || $user->hasScopedRole('DIRECTOR', $unitId));
    }

    public function authorityInitiatedParticipants(User $user, MeetingRequest $request): bool
    {
        $officeId = (int) $request->authorityContext()->value('authority_office_unit_id');
        return $request->flow_type === 'HIGH_AUTHORITY_INITIATED' && $user->isActive() && $user->can('authority-participant.manage') && $officeId && ($user->hasScopedRole('AUTHORITY_OFFICE', $officeId));
    }

    public function authorityInitiatedFinalize(User $user, MeetingRequest $request): bool
    {
        return $this->authorityInitiatedSchedule($user, $request) && $user->can('authority-meeting.finalize');
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->can('meeting-request.create') && (filled($user->scopedRoleUnitIds('EXECUTIVE_MANAGEMENT')));
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
        return $user->isActive() && $user->can('meeting.schedule') && in_array($request->status, ['APPROVED', 'SCHEDULING', 'SCHEDULE_CONFIRMED'], true) && ($request->created_by === $user->id || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $request->requesting_unit_id));
    }

    public function finalize(User $user, MeetingRequest $request): bool
    {
        return $user->isActive() && $user->can('meeting.create') && ($request->created_by === $user->id || $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $request->requesting_unit_id));
    }

    public function delete(User $user, MeetingRequest $request): bool
    {
        return $user->isActive() && $user->can('meeting-request.update') && $request->created_by === $user->id && $request->status === 'DRAFT';
    }
}
