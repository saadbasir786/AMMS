<?php

declare(strict_types=1);

namespace App\Actions\Flow13;

use App\Actions\Flow13\Concerns\Flow13ActionSupport;
use App\Actions\Flow13\Concerns\Flow13MeetingStateSupport;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingExceptionCase;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;
use App\Support\ScheduleConflictGuard;
use App\Support\ScheduleVersionService;
use Illuminate\Support\Facades\DB;

class ResumePostponedMeeting
{
    use Flow13ActionSupport, Flow13MeetingStateSupport;

    public function __construct(private MeetingExceptionGovernanceResolver $governance, private ScheduleVersionService $scheduleVersions, private ScheduleConflictGuard $conflicts) {}

    public function execute(MeetingExceptionCase $case, User $actor, array $schedule): MeetingExceptionCase
    {
        $case->loadMissing(['meeting', 'currentVersion']);
        abort_unless($case->case_type === MeetingExceptionCase::RESUMPTION && $this->governance->mayApply($case, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');
        return DB::transaction(function () use ($case, $actor, $schedule): MeetingExceptionCase {
            $lockedCase = MeetingExceptionCase::query()->with(['meeting.request', 'currentVersion'])->lockForUpdate()->findOrFail($case->id);
            $meeting = Meeting::query()->with(['request', 'currentScheduleVersion', 'scheduleVersions', 'currentParticipantVersion', 'participants'])->lockForUpdate()->findOrFail($lockedCase->meeting_id);
            abort_unless($lockedCase->status === MeetingExceptionCase::READY_TO_APPLY, 409, 'MEETING_EXCEPTION_REVIEW_INCOMPLETE');
            abort_unless($meeting->status === 'POSTPONED', 409, 'MEETING_RESUMPTION_NOT_ALLOWED');
            $this->assertBaseline($lockedCase, $meeting);
            $normalized = $this->scheduleVersions->normalize($schedule);
            $current = $this->scheduleVersions->current($meeting);
            abort_if($this->scheduleVersions->sameAs($current, $normalized), 422, 'MEETING_RESUMPTION_SCHEDULE_REQUIRED');
            $employeeIds = $this->conflicts->employeeIds($meeting);
            Employee::query()->whereIn('id', $employeeIds)->orderBy('id')->lockForUpdate()->get();
            $this->conflicts->assertNoConflict($meeting, $normalized, $actor);
            $newVersion = $this->scheduleVersions->createNext($meeting, $normalized, $actor->id);
            $this->scheduleVersions->applyToMeeting($meeting, $newVersion);
            $meeting->forceFill(['status' => 'SCHEDULED'])->save();
            $lockedCase->forceFill(['status' => MeetingExceptionCase::APPLIED, 'active_marker' => null, 'applied_by_user_id' => $actor->id, 'applied_at' => now(), 'updated_at' => now()])->save();
            $metadata = $this->exceptionMetadata($lockedCase) + ['old_schedule_version_id' => $current->id, 'new_schedule_version_id' => $newVersion->id, 'new_schedule' => $this->scheduleVersions->snapshot($newVersion)];
            $this->audit($meeting, $actor, 'MEETING_RESUMED', $lockedCase->currentVersion->reason, $metadata);
            $this->notify($meeting->coordinator_id, 'MEETING_RESUMED', 'جلسه دوباره برنامه‌ریزی شد', 'برای جلسه زمان‌بندی رسمی جدید ایجاد شد و جلسه فعال گردید.', ['meeting_id' => $meeting->id, 'meeting_number' => $meeting->meeting_number, 'schedule_version_id' => $newVersion->id]);
            return $lockedCase->fresh(['meeting.currentScheduleVersion', 'currentVersion']);
        });
    }
}
