<?php

namespace App\Policies;

use App\Models\MeetingRequest;
use App\Models\User;

class FlowAwareMeetingRequestPolicy extends MeetingRequestPolicy
{
    public function update(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTERNAL_DIRECTORATE') {
            return parent::update($user, $request);
        }

        return $user->isActive()
            && $user->can('meeting-request.update')
            && $request->created_by === $user->id
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
            && in_array($request->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true);
    }

    public function submit(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTERNAL_DIRECTORATE') {
            return parent::submit($user, $request);
        }

        return $user->isActive()
            && ($user->can('meeting-request.submit') || $user->can('meeting-request.resubmit'))
            && $request->created_by === $user->id
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
            && in_array($request->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true);
    }

    public function schedule(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTERNAL_DIRECTORATE') {
            return parent::schedule($user, $request);
        }

        return $user->isActive()
            && $user->can('meeting.schedule')
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
            && in_array($request->status, ['APPROVED', 'SCHEDULING'], true);
    }

    public function finalize(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTERNAL_DIRECTORATE') {
            return parent::finalize($user, $request);
        }

        return $user->isActive()
            && $user->can('meeting.create')
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
            && $request->status === 'SCHEDULE_CONFIRMED';
    }

    public function delete(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTERNAL_DIRECTORATE') {
            return parent::delete($user, $request);
        }

        return $user->isActive()
            && $user->can('meeting-request.update')
            && $request->created_by === $user->id
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
            && $request->status === 'DRAFT';
    }

    public function flow02RequesterEdit(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'INTER_DIRECTORATE') return false;
        $unitId = (int) $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $user->isActive()
            && $user->can('meeting-request.update')
            && $unitId > 0
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
        $requesterId = (int) $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $requesterId > 0 && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $requesterId);
    }

    public function flow03RequesterEdit(User $user, MeetingRequest $request): bool
    {
        if ($request->flow_type !== 'MULTI_DIRECTORATE') return false;
        $unitId = (int) $request->units()->where('role', 'REQUESTER')->value('organizational_unit_id');
        return $user->isActive()
            && $user->can('meeting-request.update')
            && $unitId > 0
            && $user->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId)
            && in_array($request->status, ['DRAFT', 'RETURNED_TO_REQUESTER', 'TARGET_ACTION_REQUIRED'], true);
    }
}
