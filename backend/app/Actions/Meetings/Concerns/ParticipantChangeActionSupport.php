<?php

namespace App\Actions\Meetings\Concerns;

use App\Models\AmmsNotification;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingAction;
use App\Models\MeetingParticipantChangeCase;
use App\Models\User;
use App\Support\ParticipantChangeValidator;
use App\Support\ParticipantGovernanceResolver;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ParticipantChangeActionSupport
{
    private function lockMeeting(int|Meeting $meeting): Meeting
    {
        $id = $meeting instanceof Meeting ? $meeting->id : $meeting;
        return Meeting::query()->lockForUpdate()->with([
            'request', 'units.unit', 'participants.employee.unit', 'authorityContext.authorityEmployee',
            'currentScheduleVersion', 'currentParticipantVersion.members.employee.unit',
        ])->findOrFail($id);
    }

    private function lockCase(Meeting $meeting, int|MeetingParticipantChangeCase $case): MeetingParticipantChangeCase
    {
        $id = $case instanceof MeetingParticipantChangeCase ? $case->id : $case;
        $locked = MeetingParticipantChangeCase::query()->lockForUpdate()->with([
            'meeting', 'ownerUnit', 'items.oldEmployee.unit', 'items.newEmployee.unit', 'baselineParticipantVersion.members.employee.unit',
        ])->where('meeting_id', $meeting->id)->findOrFail($id);
        return $locked;
    }

    private function assertActive(User $actor): void
    {
        if (!$actor->isActive()) throw new HttpException(403, 'INACTIVE_USER_NOT_ALLOWED');
    }

    private function assertManage(User $actor, MeetingParticipantChangeCase $case, ParticipantGovernanceResolver $governance): void
    {
        $this->assertActive($actor);
        if (!$governance->mayPrepare($case->meeting, $actor, $case->owner_type, $case->owner_unit_id)) throw new HttpException(403, 'UNAUTHORIZED_PARTICIPANT_CHANGE');
    }

    private function assertConfirm(User $actor, MeetingParticipantChangeCase $case, ParticipantGovernanceResolver $governance): void
    {
        $this->assertActive($actor);
        if (!$governance->mayConfirm($case->meeting, $actor, $case->owner_type, $case->owner_unit_id, (int) $case->initiated_by_user_id)) throw new HttpException(403, 'UNAUTHORIZED_PARTICIPANT_CONFIRMATION');
    }

    private function assertState(MeetingParticipantChangeCase $case, array $states): void
    {
        if (!in_array($case->status, $states, true)) throw new HttpException(422, 'PARTICIPANT_CHANGE_NOT_READY');
    }

    private function record(Meeting $meeting, User $actor, string $action, ?string $comment = null, array $metadata = []): void
    {
        $metadata = $this->addParticipantSnapshots($metadata);
        MeetingAction::create([
            'meeting_id' => $meeting->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'comment' => $comment,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function addParticipantSnapshots(array $metadata): array
    {
        $operations = is_array($metadata['operations'] ?? null) ? $metadata['operations'] : [];
        $employeeIds = collect([
            $metadata['old_employee_id'] ?? null,
            $metadata['new_employee_id'] ?? null,
        ])->merge(collect($operations)->flatMap(fn (array $operation): array => [
            $operation['old_employee_id'] ?? null,
            $operation['new_employee_id'] ?? null,
        ]))->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($employeeIds->isEmpty()) return $metadata;

        $employees = Employee::query()->with('unit')->whereIn('id', $employeeIds)->get()->keyBy('id');
        $snapshot = static function ($employee) use ($employees): ?array {
            if (!$employee) return null;
            $employee = $employees->get((int) $employee);
            if (!$employee) return null;

            return [
                'id' => (int) $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'unit_name' => $employee->unit?->name,
            ];
        };

        foreach (['old', 'new'] as $side) {
            $key = "{$side}_employee";
            $idKey = "{$side}_employee_id";
            if (!isset($metadata[$key]) && !empty($metadata[$idKey])) {
                $metadata[$key] = $snapshot($metadata[$idKey]);
            }
        }

        if (isset($metadata['operations']) && is_array($metadata['operations'])) {
            $metadata['operations'] = array_map(function (array $operation) use ($snapshot): array {
                foreach (['old', 'new'] as $side) {
                    $key = "{$side}_employee";
                    $idKey = "{$side}_employee_id";
                    if (!isset($operation[$key]) && !empty($operation[$idKey])) {
                        $operation[$key] = $snapshot($operation[$idKey]);
                    }
                }
                return $operation;
            }, $metadata['operations']);
        }

        return $metadata;
    }

    private function notifyEmployee(int $employeeId, string $type, string $title, string $message, Meeting $meeting): void
    {
        $userId = DB::table('users')->where('employee_id', $employeeId)->value('id');
        if ($userId) AmmsNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => ['meeting_id' => $meeting->id],
        ]);
    }

    private function notifyScopedRole(int $unitId, string $role, string $type, string $title, string $message, Meeting $meeting, ?int $excludeUserId = null): void
    {
        $users = User::query()->where('status', 'ACTIVE')
            ->whereHas('roles', fn ($query) => $query->where('name', $role)->where('guard_name', 'sanctum'))
            ->whereHas('roleScopes', fn ($query) => $query->where('organizational_unit_id', $unitId)
                ->whereHas('role', fn ($roles) => $roles->where('name', $role)->where('guard_name', 'sanctum')))
            ->when($excludeUserId, fn ($query) => $query->where('id', '<>', $excludeUserId))
            ->get(['id']);

        foreach ($users as $user) {
            AmmsNotification::create([
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => ['meeting_id' => $meeting->id],
            ]);
        }
    }

    private function notifyUser(int $userId, string $type, string $title, string $message, Meeting $meeting): void
    {
        if ($userId < 1) return;
        AmmsNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => ['meeting_id' => $meeting->id],
        ]);
    }

    private function lockEmployeesForItems(MeetingParticipantChangeCase $case): void
    {
        $ids = $case->items->flatMap(fn ($item) => [$item->old_employee_id, $item->new_employee_id])->filter()->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        if ($ids !== []) Employee::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
    }

    private function validator(): ParticipantChangeValidator
    {
        return app(ParticipantChangeValidator::class);
    }

    private function governance(): ParticipantGovernanceResolver
    {
        return app(ParticipantGovernanceResolver::class);
    }
}
