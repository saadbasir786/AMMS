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
}
