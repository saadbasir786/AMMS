<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AmmsCapabilityScope;
use App\Models\MeetingRequest;
use App\Models\User;

class InternalMeetingApprovalDelegation
{
    public function scopeFor(User $actor, MeetingRequest $request): ?AmmsCapabilityScope
    {
        if (
            !$actor->isActive()
            || $request->flow_type !== 'INTERNAL_DIRECTORATE'
            || $request->status !== 'PENDING_INTERNAL_APPROVAL'
            || !$actor->can('meeting-request.approve-delegated')
            || !$actor->hasScopedRole('EXECUTIVE_MANAGEMENT', (int) $request->requesting_unit_id)
        ) {
            return null;
        }

        return AmmsCapabilityScope::query()
            ->active()
            ->where('user_id', $actor->id)
            ->where('capability', SpecialCapability::INTERNAL_MEETING_REQUEST_APPROVAL)
            ->where('organizational_unit_id', $request->requesting_unit_id)
            ->first();
    }
}
