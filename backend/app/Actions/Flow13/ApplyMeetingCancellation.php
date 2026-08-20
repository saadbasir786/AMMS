<?php

declare(strict_types=1);

namespace App\Actions\Flow13;

use App\Actions\Flow13\Concerns\Flow13ActionSupport;
use App\Actions\Flow13\Concerns\Flow13MeetingStateSupport;
use App\Models\Meeting;
use App\Models\MeetingAttendanceInvalidation;
use App\Models\MeetingExceptionCase;
use App\Models\User;
use App\Support\MeetingExceptionGovernanceResolver;
use Illuminate\Support\Facades\DB;

class ApplyMeetingCancellation
{
    use Flow13ActionSupport, Flow13MeetingStateSupport;

    public function __construct(private MeetingExceptionGovernanceResolver $governance) {}

    public function execute(MeetingExceptionCase $case, User $actor): MeetingExceptionCase
    {
        $case->loadMissing(['meeting', 'currentVersion']);
        abort_unless($case->case_type === MeetingExceptionCase::CANCELLATION && $this->governance->mayApply($case, $actor), 403, 'UNAUTHORIZED_MEETING_EXCEPTION');
        return DB::transaction(function () use ($case, $actor): MeetingExceptionCase {
            $locked = MeetingExceptionCase::query()->with(['meeting.request', 'currentVersion'])->lockForUpdate()->findOrFail($case->id);
            $meeting = Meeting::query()->with(['request', 'terminalAssignments', 'attendanceRecords'])->lockForUpdate()->findOrFail($locked->meeting_id);
            abort_unless($locked->status === MeetingExceptionCase::READY_TO_APPLY, 409, 'MEETING_EXCEPTION_REVIEW_INCOMPLETE');
            abort_unless(in_array($meeting->status, ['SCHEDULED', 'POSTPONED'], true), 409, $meeting->status === 'IN_PROGRESS' ? 'MEETING_EXECUTION_ALREADY_STARTED' : 'MEETING_CANCELLATION_NOT_ALLOWED');
            $this->assertBaseline($locked, $meeting);
            $meeting->forceFill(['status' => 'CANCELLED'])->save();
            $this->revokeTerminalAccess($meeting, $actor, 'MEETING_CANCELLED');
            $this->invalidateAttendance($meeting, $locked, $actor, MeetingAttendanceInvalidation::CANCELLED);
            $locked->forceFill(['status' => MeetingExceptionCase::APPLIED, 'active_marker' => null, 'applied_by_user_id' => $actor->id, 'applied_at' => now(), 'updated_at' => now()])->save();
            $this->audit($meeting, $actor, 'MEETING_CANCELLED', $locked->currentVersion->reason, $this->exceptionMetadata($locked));
            $this->notify($meeting->coordinator_id, 'MEETING_CANCELLED', 'جلسه لغو شد', 'جلسه لغو شد؛ سوابق آن حذف نشده است.', ['meeting_id' => $meeting->id, 'meeting_number' => $meeting->meeting_number]);
            return $locked->fresh(['meeting', 'currentVersion']);
        });
    }
}
