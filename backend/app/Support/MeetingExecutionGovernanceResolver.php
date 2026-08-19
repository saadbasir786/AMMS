<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Meeting;
use App\Models\User;

class MeetingExecutionGovernanceResolver
{
    public function resolve(Meeting $meeting): array
    {
        $flowType = (string) ($meeting->request?->flow_type ?? '');
        $authorityControlled = in_array($flowType, [
            'DIRECTORATE_WITH_HIGH_AUTHORITY',
            'MULTI_DIRECTORATE_WITH_HIGH_AUTHORITY',
            'HIGH_AUTHORITY_INITIATED',
        ], true) || $meeting->authorityContext()->exists();

        return [
            'flow_type' => $flowType,
            'authority_controlled' => $authorityControlled,
            'authority_office_unit_id' => (int) ($meeting->request?->authorityContext?->authority_office_unit_id ?? $meeting->authorityContext?->authority_office_unit_id ?? 0),
            'organizing_unit_id' => (int) $meeting->organizing_unit_id,
        ];
    }

    public function mayStart(Meeting $meeting, User $actor): bool
    {
        return $this->mayOperate($meeting, $actor, 'meeting.execution.start');
    }

    public function mayEnd(Meeting $meeting, User $actor): bool
    {
        return $this->mayOperate($meeting, $actor, 'meeting.execution.end');
    }

    private function mayOperate(Meeting $meeting, User $actor, string $permission): bool
    {
        if (!$actor->isActive() || !$actor->can($permission)) return false;
        if (!app(\App\Policies\MeetingPolicy::class)->view($actor, $meeting)) return false;

        $governance = $this->resolve($meeting);
        if ($governance['authority_controlled']) {
            return $governance['authority_office_unit_id'] > 0
                && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']);
        }

        return (int) $meeting->coordinator_id === (int) $actor->id
            || $actor->hasScopedRole('MEETING_COORDINATOR', $governance['organizing_unit_id']);
    }
}
