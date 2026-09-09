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

trait MultiDirectorateWorkflowSupport
{
    private function lockMultiDirectorateRequest(int|MeetingRequest $request): MeetingRequest
    {
        $id = $request instanceof MeetingRequest ? $request->id : $request;
        $locked = MeetingRequest::query()->lockForUpdate()->findOrFail($id);
        abort_unless($locked->flow_type === 'MULTI_DIRECTORATE', 422, 'MULTI_DIRECTORATE_REQUEST_REQUIRED');

        return $locked->load([
            'units.unit',
            'requestingUnit',
            'creator.employee',
            'participants.employee.unit',
            'nominations.employee.unit',
            'scheduleProposals.proposedByUnit',
            'scheduleProposals.acceptances.unit',
            'unitReviews.unit',
            'participantConfirmations.unit',
            'actions.actor',
            'attachments',
            'meeting.units.unit',
        ]);
    }

    private function requesterUnit(MeetingRequest $request): OrganizationalUnit
    {
        return $request->units->firstWhere('role', 'REQUESTER')?->unit ?? abort(422, 'INVALID_REQUEST_UNITS');
    }

    private function targetUnits(MeetingRequest $request): Collection
    {
        return $request->units->where('role', 'TARGET')->pluck('unit')->filter()->values();
    }

    private function participatingUnits(MeetingRequest $request): Collection
    {
        return $request->units->whereIn('role', ['REQUESTER', 'TARGET'])->pluck('unit')->filter()->values();
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
                $query->where('organizational_unit_id', $unitId)
                    ->whereHas('role', fn ($role) => $role->whereIn('name', $roles)->where('guard_name', 'sanctum'));
            })
            ->get()
            ->filter(fn (User $user) => $user->isActive());

        foreach ($users as $user) {
            AmmsNotification::create([
                'user_id' => $user->id,
                'type' => 'MULTI_DIRECTORATE_MEETING_REQUEST',
                'title' => $title,
                'message' => $message,
                'data' => ['meeting_request_id' => $request->id, 'organizational_unit_id' => $unitId],
            ]);
        }
    }

    private function notifyUser(?int $userId, string $title, string $message, MeetingRequest $request): void
    {
        if (!$userId) return;
        AmmsNotification::create([
            'user_id' => $userId,
            'type' => 'MULTI_DIRECTORATE_MEETING_REQUEST',
            'title' => $title,
            'message' => $message,
            'data' => ['meeting_request_id' => $request->id],
        ]);
    }

    private function canManageUnit(User $actor, int $unitId): bool
    {
        return $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    private function canCoordinateUnit(User $actor, int $unitId): bool
    {
        return $actor->hasScopedRole('EXECUTIVE_MANAGEMENT', $unitId);
    }

    private function assertMultiUnitSet(Collection $units): void
    {
        $requesters = $units->where('role', 'REQUESTER');
        $targets = $units->where('role', 'TARGET');
        $targetIds = $targets->pluck('organizational_unit_id')->all();

        abort_unless($requesters->count() === 1, 422, 'EXACTLY_ONE_REQUESTER_REQUIRED');
        abort_if(count($targetIds) !== count(array_unique($targetIds)), 422, 'DUPLICATE_TARGET_DIRECTORATE');
        abort_unless($targets->count() >= 2, 422, 'MINIMUM_THREE_DIRECTORATES_REQUIRED');

        $requester = $requesters->first()->unit;
        abort_unless($requester, 422, 'INVALID_REQUEST_UNITS');
        foreach ($units->pluck('unit')->filter() as $unit) {
            abort_unless($unit->status === 'ACTIVE' && $unit->isFormalGovernanceUnit(), 422, 'INVALID_TARGET_DIRECTORATE');
            abort_unless($unit->organization_id === $requester->organization_id, 422, 'CROSS_ORGANIZATION_MEETING_NOT_ALLOWED');
        }
        abort_if($targets->contains(fn ($row) => (int) $row->organizational_unit_id === (int) $requester->id), 422, 'REQUESTER_CANNOT_BE_TARGET');
    }

    private function assertTargetMember(MeetingRequest $request, int $unitId): OrganizationalUnit
    {
        $unit = $request->units->firstWhere(fn ($row) => $row->role === 'TARGET' && (int) $row->organizational_unit_id === $unitId)?->unit;
        abort_unless($unit, 403, 'TARGET_UNIT_NOT_PART_OF_REQUEST');
        return $unit;
    }

    private function assertParticipatingMember(MeetingRequest $request, int $unitId): OrganizationalUnit
    {
        $unit = $request->units->firstWhere(fn ($row) => in_array($row->role, ['REQUESTER', 'TARGET'], true) && (int) $row->organizational_unit_id === $unitId)?->unit;
        abort_unless($unit, 403, 'UNIT_NOT_PART_OF_REQUEST');
        return $unit;
    }

    private function assertParticipantEmployees(array $employeeIds, MeetingRequest|Collection $requestOrUnits): void
    {
        $units = $requestOrUnits instanceof MeetingRequest ? $this->participatingUnits($requestOrUnits) : $requestOrUnits;
        $ids = array_values(array_unique(array_map('intval', $employeeIds)));
        if (!$ids) return;

        $employees = Employee::query()->with('unit.parent')->whereIn('id', $ids)->get();
        abort_if($employees->count() !== count($ids), 422, 'INVALID_PARTICIPANT');
        foreach ($employees as $employee) {
            abort_unless($employee->status === 'ACTIVE', 422, 'INVALID_PARTICIPANT');
            $belongs = $units->contains(fn (OrganizationalUnit $unit) => $unit->isWithinScope($employee->organizational_unit_id));
            abort_unless($belongs, 422, 'PARTICIPANT_OUTSIDE_MULTI_DIRECTORATE_SCOPE');
        }
    }

    protected function topDirectorate(int $unitId): ?OrganizationalUnit
    {
        $unit = OrganizationalUnit::find($unitId);
        return $unit?->formalGovernanceUnit();
    }

    private function ensureAuthorizedApproverExists(int $unitId): void
    {
        abort_unless(User::whereHas('roleScopes', fn ($q) => $q->where('organizational_unit_id', $unitId)->whereHas('role', fn ($role) => $role->where('name', 'DIRECTOR')->where('guard_name', 'sanctum')))->exists(), 422, 'UNAUTHORIZED_APPROVER');
    }

    private function allTargetsApproved(MeetingRequest $request): bool
    {
        $targetIds = $request->units->where('role', 'TARGET')->pluck('organizational_unit_id');
        return $targetIds->isNotEmpty()
            && $request->unitReviews->whereIn('organizational_unit_id', $targetIds)->count() === $targetIds->count()
            && $request->unitReviews->whereIn('organizational_unit_id', $targetIds)->every(fn ($review) => $review->review_status === 'APPROVED');
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
        return $request->fresh([
            'units.unit',
            'requestingUnit',
            'creator.employee',
            'participants.employee.unit',
            'nominations.employee.unit',
            'scheduleProposals.proposedByUnit',
            'scheduleProposals.acceptances.unit',
            'unitReviews.unit',
            'participantConfirmations.unit',
            'actions.actor',
            'attachments',
            'meeting.units.unit',
        ]);
    }
}
