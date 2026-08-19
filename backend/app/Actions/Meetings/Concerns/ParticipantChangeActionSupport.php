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
            'meeting', 'ownerUnit', 'items.oldEmployee', 'items.newEmployee', 'baselineParticipantVersion.members.employee.unit',
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
        if (!$governance->mayConfirm($case->meeting, $actor, $case->owner_type, $case->owner_unit_id)) throw new HttpException(403, 'UNAUTHORIZED_PARTICIPANT_CONFIRMATION');
    }

    private function assertState(MeetingParticipantChangeCase $case, array $states): void
    {
        if (!in_array($case->status, $states, true)) throw new HttpException(422, 'PARTICIPANT_CHANGE_NOT_READY');
    }

    private function record(Meeting $meeting, User $actor, string $action, ?string $comment = null, array $metadata = []): void
    {
        MeetingAction::create([
            'meeting_id' => $meeting->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'comment' => $comment,
            'metadata' => $metadata ?: null,
        ]);
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
