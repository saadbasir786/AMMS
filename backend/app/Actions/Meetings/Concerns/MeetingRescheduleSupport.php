<?php

namespace App\Actions\Meetings\Concerns;

use App\Models\Meeting;
use App\Models\AmmsNotification;
use App\Models\MeetingAction;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingRescheduleProposal;
use App\Models\User;
use App\Support\ScheduleGovernanceResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait MeetingRescheduleSupport
{
    private function lockMeeting(Meeting|int $meeting): Meeting
    {
        $id = $meeting instanceof Meeting ? $meeting->id : $meeting;
        return Meeting::query()->lockForUpdate()->with([
            'request', 'units', 'participants', 'authorityContext', 'currentScheduleVersion',
        ])->findOrFail($id);
    }

    private function ensureReschedulable(Meeting $meeting): void
    {
        abort_unless($meeting->request?->status === 'CONVERTED_TO_MEETING', 422, 'MEETING_NOT_RESCHEDULABLE');
        if ($meeting->status !== 'SCHEDULED') {
            abort(422, in_array($meeting->status, ['IN_PROGRESS', 'HELD', 'COMPLETED'], true) ? 'MEETING_ALREADY_STARTED' : 'MEETING_NOT_RESCHEDULABLE');
        }
        $current = app(\App\Support\ScheduleVersionService::class)->current($meeting);
        $at = CarbonImmutable::createFromFormat('Y-m-d H:i', $current->meeting_date->toDateString().' '.substr((string) $current->start_time, 0, 5), \App\Support\ScheduleVersionService::TIMEZONE);
        abort_unless($at && $at->greaterThan(CarbonImmutable::now(\App\Support\ScheduleVersionService::TIMEZONE)), 422, 'MEETING_ALREADY_STARTED');
    }

    private function currentCase(Meeting $meeting): ?MeetingRescheduleCase
    {
        return $meeting->active_reschedule_case_id
            ? MeetingRescheduleCase::with(['proposals.acknowledgements', 'alternativeRequests'])->find($meeting->active_reschedule_case_id)
            : null;
    }

    private function activeProposal(MeetingRescheduleCase $case): ?MeetingRescheduleProposal
    {
        return $case->proposals()->where('status', 'ACTIVE')->latest('id')->first();
    }

    private function recordMeetingAction(Meeting $meeting, User $actor, string $action, ?string $comment = null, array $metadata = []): MeetingAction
    {
        return MeetingAction::create([
            'meeting_id' => $meeting->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'comment' => $comment,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    private function actorUnitId(User $actor): ?int
    {
        return $actor->employee?->organizational_unit_id ? (int) $actor->employee->organizational_unit_id : null;
    }

    private function actorFormalUnitId(User $actor, array $requiredUnitIds, array $roles = ['EXECUTIVE_MANAGEMENT', 'DIRECTOR']): ?int
    {
        foreach (array_values(array_unique(array_map('intval', $requiredUnitIds))) as $unitId) {
            foreach ($roles as $role) {
                if ($actor->hasScopedRole($role, $unitId)) return $unitId;
            }
        }
        return null;
    }

    private function ensureNoActiveCase(Meeting $meeting): void
    {
        abort_if($meeting->active_reschedule_case_id, 422, 'RESCHEDULE_ALREADY_IN_PROGRESS');
    }

    private function ensureCaseActive(MeetingRescheduleCase $case): void
    {
        abort_unless(in_array($case->status, ['OPEN', 'NEGOTIATING', 'READY_TO_CONFIRM'], true), 422, 'RESCHEDULE_CASE_NOT_ACTIVE');
    }

    private function resolver(Meeting $meeting): array
    {
        return app(ScheduleGovernanceResolver::class)->resolve($meeting);
    }

    private function notifyUsers(array $userIds, string $type, string $title, string $message, array $data = []): void
    {
        foreach (array_values(array_unique(array_filter(array_map('intval', $userIds)))) as $userId) {
            AmmsNotification::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'message' => $message, 'data' => $data]);
        }
    }

    private function notifyFormalUnits(Meeting $meeting, array $unitIds, string $type, string $title, string $message, array $data = []): void
    {
        $unitIds = array_values(array_unique(array_map('intval', $unitIds)));
        $userIds = User::query()->whereHas('roleScopes', function ($query) use ($unitIds): void {
            $query->whereIn('organizational_unit_id', $unitIds)
                ->whereHas('role', fn ($role) => $role->whereIn('name', ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'])->where('guard_name', 'sanctum'));
        })->get()->filter(fn (User $user) => $user->isActive())->pluck('id')->all();
        $this->notifyUsers($userIds, $type, $title, $message, $data + ['meeting_id' => $meeting->id]);
    }

    private function authoritySchedulerIds(int $officeUnitId): array
    {
        return User::query()->whereHas('roleScopes', function ($query) use ($officeUnitId): void {
            $query->where('organizational_unit_id', $officeUnitId)
                ->whereHas('role', fn ($role) => $role->where('name', 'EXECUTIVE_SECRETARY')->where('guard_name', 'sanctum'));
        })->get()->filter(fn (User $user) => $user->isActive())->pluck('id')->all();
    }

    private function meetingNotificationUserIds(Meeting $meeting, array $requiredUnitIds = []): array
    {
        $employeeIds = $meeting->participants()->pluck('employee_id')->map(fn ($id): int => (int) $id)->all();
        $employeeIds[] = (int) $meeting->chairperson_employee_id;
        if ($meeting->authorityContext?->authority_employee_id) $employeeIds[] = (int) $meeting->authorityContext->authority_employee_id;
        $userIds = User::whereIn('employee_id', array_values(array_unique($employeeIds)))->pluck('id')->all();
        if ($requiredUnitIds !== []) {
            $requiredUnitIds = array_values(array_unique(array_map('intval', $requiredUnitIds)));
            $userIds = array_merge($userIds, User::query()->whereHas('roleScopes', function ($query) use ($requiredUnitIds): void {
                $query->whereIn('organizational_unit_id', $requiredUnitIds)
                    ->whereHas('role', fn ($role) => $role->whereIn('name', ['EXECUTIVE_MANAGEMENT', 'DIRECTOR'])->where('guard_name', 'sanctum'));
            })->get()->filter(fn (User $user) => $user->isActive())->pluck('id')->all());
        }
        return array_values(array_unique(array_filter($userIds)));
    }
}
