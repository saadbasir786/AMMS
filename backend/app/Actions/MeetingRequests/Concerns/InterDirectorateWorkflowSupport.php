<?php

namespace App\Actions\MeetingRequests\Concerns;

use App\Enums\MeetingRequestStatus;
use App\Models\AmmsNotification;
use App\Models\Employee;
use App\Models\MeetingRequest;
use App\Models\MeetingRequestAction;
use App\Models\OrganizationalUnit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait InterDirectorateWorkflowSupport
{
    private function lockFlow02Request(int|MeetingRequest $request): MeetingRequest
    {
        $id = $request instanceof MeetingRequest ? $request->id : $request;
        $locked = MeetingRequest::query()->lockForUpdate()->findOrFail($id);
        abort_unless($locked->flow_type === 'INTER_DIRECTORATE', 422, 'FLOW02_REQUEST_REQUIRED');
        return $locked->load(['units.unit', 'requestingUnit', 'creator.employee', 'participants.employee', 'nominations.employee', 'scheduleProposals.proposedByUnit']);
    }

    private function unitFor(MeetingRequest $request, string $role): OrganizationalUnit
    {
        return $request->units->firstWhere('role', $role)?->unit ?? abort(422, 'INVALID_REQUEST_UNITS');
    }

    private function assertActiveActor(User $actor): void
    {
        abort_unless($actor->isActive(), 403, 'UNAUTHORIZED');
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
        MeetingRequestAction::create([
            'meeting_request_id' => $request->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'comment' => $comment,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function notifyUnit(int $unitId, array $roles, string $title, string $message, MeetingRequest $request): void
    {
        $users = User::query()
            ->whereHas('roleScopes', function ($query) use ($unitId, $roles): void {
                $query->where('organizational_unit_id', $unitId)->whereHas('role', fn ($role) => $role->whereIn('name', $roles)->where('guard_name', 'sanctum'));
            })
            ->get()
            ->filter(fn (User $user) => $user->isActive());

        foreach ($users as $user) {
            AmmsNotification::create(['user_id' => $user->id, 'type' => 'INTER_DIRECTORATE_MEETING_REQUEST', 'title' => $title, 'message' => $message, 'data' => ['meeting_request_id' => $request->id]]);
        }
    }

    private function notifyUser(?int $userId, string $title, string $message, MeetingRequest $request): void
    {
        if (!$userId) return;
        AmmsNotification::create(['user_id' => $userId, 'type' => 'INTER_DIRECTORATE_MEETING_REQUEST', 'title' => $title, 'message' => $message, 'data' => ['meeting_request_id' => $request->id]]);
    }

    private function canManageUnit(User $actor, int $unitId): bool
    {
        return $actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    private function canCoordinateUnit(User $actor, int $unitId): bool
    {
        return $actor->hasScopedRole('SYSTEM_ADMIN') || $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    private function assertParticipantEmployees(array $employeeIds, OrganizationalUnit $requester, OrganizationalUnit $target): void
    {
        $allowed = $employeeIds ? Employee::with('unit.parent')->whereIn('id', array_unique($employeeIds))->get() : collect();
        abort_if($allowed->count() !== count(array_unique($employeeIds)), 422, 'INVALID_PARTICIPANT');
        foreach ($allowed as $employee) {
            abort_if($employee->status !== 'ACTIVE', 422, 'INVALID_PARTICIPANT');
            $belongsToRequester = $requester->load('children')->isWithinScope($employee->organizational_unit_id);
            $belongsToTarget = $target->load('children')->isWithinScope($employee->organizational_unit_id);
            if (!$belongsToRequester && !$belongsToTarget) {
                $third = $this->topDirectorate($employee->organizational_unit_id);
                if ($third && $third->organization_id === $requester->organization_id && $third->id !== $requester->id && $third->id !== $target->id) abort(422, 'THIRD_DIRECTORATE_REQUIRES_MULTI_DIRECTORATE_FLOW');
                abort(422, 'PARTICIPANT_OUTSIDE_ALLOWED_UNITS');
            }
        }
    }

    private function topDirectorate(int $unitId): ?OrganizationalUnit
    {
        $unit = OrganizationalUnit::find($unitId);
        $visited = [];
        while ($unit && $unit->parent_id && !in_array($unit->id, $visited, true)) {
            $visited[] = $unit->id;
            $unit = $unit->parent;
        }
        return $unit?->unit_type === 'DIRECTORATE' ? $unit : null;
    }

    private function ensureSameOrganizationAndDistinct(OrganizationalUnit $requester, OrganizationalUnit $target): void
    {
        abort_unless($requester->status === 'ACTIVE' && $target->status === 'ACTIVE', 422, 'INVALID_TARGET_DIRECTORATE');
        abort_if($requester->id === $target->id, 422, 'SAME_REQUESTER_AND_TARGET');
        abort_unless($requester->organization_id === $target->organization_id, 422, 'INVALID_TARGET_DIRECTORATE');
        abort_unless($requester->unit_type === 'DIRECTORATE' && $target->unit_type === 'DIRECTORATE', 422, 'INVALID_TARGET_DIRECTORATE');
    }

    private function ensureAuthorizedApproverExists(int $unitId): void
    {
        abort_unless(User::whereHas('roleScopes', fn ($q) => $q->where('organizational_unit_id', $unitId)->whereHas('role', fn ($role) => $role->where('name', 'DIRECTOR')->where('guard_name', 'sanctum')))->exists(), 422, 'UNAUTHORIZED_APPROVER');
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
        return $request->fresh(['units.unit', 'requestingUnit', 'creator.employee', 'participants.employee.unit', 'nominations.employee.unit', 'scheduleProposals.proposedByUnit', 'actions.actor', 'attachments', 'meeting.units.unit']);
    }
}
