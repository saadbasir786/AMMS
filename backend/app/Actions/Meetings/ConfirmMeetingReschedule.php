<?php

namespace App\Actions\Meetings;

use App\Actions\Meetings\Concerns\MeetingRescheduleSupport;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingRescheduleCase;
use App\Models\MeetingRescheduleProposal;
use App\Models\User;
use App\Support\ScheduleConflictGuard;
use App\Support\ScheduleGovernanceResolver;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConfirmMeetingReschedule
{
    use MeetingRescheduleSupport;

    public function __invoke(User $actor, Meeting $meeting, MeetingRescheduleCase $case): Meeting
    {
        return DB::transaction(function () use ($actor, $meeting, $case): Meeting {
            $locked = $this->lockMeeting($meeting);
            $this->ensureReschedulable($locked);
            $case = $case->fresh(['baselineScheduleVersion']);
            abort_unless((int) $case->meeting_id === (int) $locked->id && (int) $locked->active_reschedule_case_id === (int) $case->id, 422, 'RESCHEDULE_CASE_NOT_ACTIVE');
            $this->ensureCaseActive($case);
            $governance = app(ScheduleGovernanceResolver::class)->resolve($locked);
            $this->assertActorCanConfirm($actor, $locked, $governance);
            $current = app(ScheduleVersionService::class)->current($locked);
            abort_unless((int) $current->id === (int) $case->baseline_schedule_version_id, 422, 'SCHEDULE_BASELINE_CHANGED');
            $proposal = $case->proposals()->where('status', 'ACTIVE')->with('acknowledgements')->latest('id')->first();
            abort_unless($proposal, 422, 'RESCHEDULE_PROPOSAL_REQUIRED');
            $required = collect($governance['required_unit_ids'])->map(fn ($id): int => (int) $id);
            $acknowledged = $proposal->acknowledgements->pluck('organizational_unit_id')->map(fn ($id): int => (int) $id);
            abort_unless($required->diff($acknowledged)->isEmpty(), 422, 'ALL_RESCHEDULE_ACKNOWLEDGEMENTS_REQUIRED');
            abort_if($case->alternativeRequests()->where('status', 'OPEN')->exists(), 422, 'UNRESOLVED_ALTERNATIVE_SCHEDULE_REQUEST');
            $schedule = app(ScheduleVersionService::class)->normalize([
                'meeting_date' => $proposal->meeting_date->toDateString(),
                'start_time' => substr((string) $proposal->start_time, 0, 5),
                'end_time' => substr((string) $proposal->end_time, 0, 5),
                'location' => $proposal->location,
            ]);
            abort_if(app(ScheduleVersionService::class)->sameAs($current, $schedule), 422, 'NO_SCHEDULE_CHANGE');
            $employeeIds = app(ScheduleConflictGuard::class)->employeeIds($locked);
            Employee::query()->whereIn('id', $employeeIds)->orderBy('id')->lockForUpdate()->get();
            app(ScheduleConflictGuard::class)->assertNoConflict($locked, $schedule, $actor);
            $newVersion = app(ScheduleVersionService::class)->createNext($locked, $schedule, $actor->id, $case);
            app(ScheduleVersionService::class)->applyToMeeting($locked, $newVersion);
            $proposal->update(['status' => 'ACCEPTED']);
            $case->update(['status' => 'CONFIRMED', 'confirmed_at' => now()]);
            $locked->update(['active_reschedule_case_id' => null]);
            $metadata = [
                'case_id' => $case->id,
                'old_schedule_version_id' => $current->id,
                'new_schedule_version_id' => $newVersion->id,
                'old_schedule' => app(ScheduleVersionService::class)->snapshot($current),
                'new_schedule' => app(ScheduleVersionService::class)->snapshot($newVersion),
                'reason' => $case->reason,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now()->toISOString(),
            ];
            $this->recordMeetingAction($locked, $actor, 'MEETING_RESCHEDULED', $case->reason, $metadata);
            $this->notifyUsers($this->meetingNotificationUserIds($locked, $governance['required_unit_ids']), 'MEETING_RESCHEDULE', 'زمان جلسه تغییر کرد', 'زمان‌بندی رسمی جلسه با موفقیت تغییر یافت.', $metadata);
            return $locked->fresh(['request', 'currentScheduleVersion', 'scheduleVersions', 'activeRescheduleCase', 'rescheduleActions']);
        });
    }

    private function assertActorCanConfirm(User $actor, Meeting $meeting, array $governance): void
    {
        if ($governance['mode'] === 'AUTHORITY_CONTROLLED') {
            abort_unless($governance['authority_office_unit_id'] && $actor->hasScopedRole('EXECUTIVE_SECRETARY', $governance['authority_office_unit_id']), 403, 'UNAUTHORIZED_RESCHEDULE_CONFIRMATION');
            return;
        }
        $unitId = $this->actorUnitId($actor);
        abort_unless($actor->id === $meeting->coordinator_id || ($unitId && in_array($unitId, $governance['required_unit_ids'], true)), 403, 'UNAUTHORIZED_RESCHEDULE_CONFIRMATION');
    }
}
